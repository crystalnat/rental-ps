import 'dart:io';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:pdf/pdf.dart';
import 'package:printing/printing.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:speech_to_text/speech_to_text.dart';

import '../models/category.dart';
import '../models/order.dart';
import '../models/payment_method.dart';
import '../models/product.dart';
import '../models/user.dart';
import '../providers/auth_provider.dart';
import '../repositories/pos_repository.dart';
import '../theme/app_colors.dart';
import '../utils/app_toast.dart';
import '../utils/receipt_pdf.dart';
import '../utils/speech_order_parser.dart';

/// Mic aktif tapi tidak ada ucapan / jeda lama → engine sering kirim
/// `error_no_match` / `error_speech_timeout`. Itu perilaku normal, bukan bug.
/// [errorMsg] kadang tidak persis sama string-nya antar perangkat/OS.
bool _isIgnorableSpeechEngineError(String errorMsg) {
  final m = errorMsg.toLowerCase();
  // error_busy: engine belum siap / sesi bertumpuk — sering saat resume cepat (mode mic kontinyu).
  return m.contains('error_no_match') ||
      m.contains('error_speech_timeout') ||
      m.contains('error_busy');
}

class TransaksiScreen extends StatefulWidget {
  const TransaksiScreen({super.key});

  @override
  State<TransaksiScreen> createState() => _TransaksiScreenState();
}

class _TransaksiScreenState extends State<TransaksiScreen> {
  List<Product> _products = [];
  List<Category> _categories = [];
  List<PaymentMethod> _paymentMethods = [];
  final List<CartItem> _cart = [];
  bool _loading = true;
  String? _error;
  String _orderType = 'walk_in';
  int? _selectedCategoryId;
  PaymentMethod? _selectedPayment;
  final _cashController = TextEditingController();
  int _pendingOrdersCount = 0;
  /// Dari server (per toko). Jika false, opsi cetak disembunyikan.
  bool _receiptPrintEnabled = false;
  /// Pilihan kasir untuk transaksi ini (hanya jika [_receiptPrintEnabled]).
  bool _wantPrintReceipt = false;

  final SpeechToText _speech = SpeechToText();
  bool _speechReady = false;
  String? _speechLocaleId;
  /// Sesi mikrofon aktif (mirip continuous mode di POS web).
  bool _micSessionActive = false;
  bool _listeningUi = false;

  @override
  void initState() {
    super.initState();
    _load();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _syncInBackground();
      _loadPendingCount();
      _initSpeech();
    });
  }

  Future<void> _loadPendingCount() async {
    try {
      final auth = context.read<AuthProvider>();
      final orders = await auth.api.getPendingOrders(storeId: auth.selectedStoreId);
      if (mounted) setState(() => _pendingOrdersCount = orders.length);
    } catch (_) {}
  }

  Future<void> _syncInBackground() async {
    try {
      final auth = context.read<AuthProvider>();
      await auth.posRepository.syncPendingOrders();
    } catch (_) {}
  }

  @override
  void dispose() {
    _micSessionActive = false;
    if (_speech.isListening) {
      _speech.stop();
    }
    _cashController.dispose();
    super.dispose();
  }

  Future<void> _initSpeech() async {
    final ok = await _speech.initialize(
      onError: (e) {
        if (!mounted) return;
        if (_isIgnorableSpeechEngineError(e.errorMsg)) return;
        AppToast.show(
          context,
          'Speech: ${e.errorMsg}',
          backgroundColor: AppColors.orangeWarning,
        );
      },
      onStatus: (status) {
        if (!_micSessionActive) {
          if (mounted) setState(() => _listeningUi = false);
          return;
        }
        if (status == 'listening') {
          if (mounted) setState(() => _listeningUi = true);
        }
        if (status == 'notListening' || status == 'done') {
          if (mounted) setState(() => _listeningUi = false);
          if (_micSessionActive) {
            Future<void>.delayed(const Duration(milliseconds: 350), () {
              if (mounted && _micSessionActive) _resumeListen();
            });
          }
        }
      },
    );
    if (!mounted) return;
    if (ok) {
      try {
        final locales = await _speech.locales();
        String? idLocale;
        for (final l in locales) {
          if (l.localeId.toLowerCase().startsWith('id')) {
            idLocale = l.localeId;
            break;
          }
        }
        _speechLocaleId = idLocale ??
            (locales.isNotEmpty ? locales.first.localeId : 'id_ID');
      } catch (_) {
        _speechLocaleId = 'id_ID';
      }
    }
    setState(() => _speechReady = ok);
  }

  Future<void> _resumeListen() async {
    if (!_speechReady || !_micSessionActive) return;
    if (_speech.isListening) return;
    try {
      await _speech.listen(
        onResult: (result) {
          if (result.finalResult) {
            final t = result.recognizedWords.trim();
            if (t.isNotEmpty) _processSpeechResult(t);
          }
        },
        listenFor: const Duration(minutes: 3),
        pauseFor: const Duration(seconds: 3),
        localeId: _speechLocaleId,
        listenOptions: SpeechListenOptions(
          partialResults: false,
          listenMode: ListenMode.dictation,
          cancelOnError: true,
        ),
      );
    } catch (e) {
      if (!mounted || !_micSessionActive) return;
      AppToast.show(
        context,
        'Gagal mendengarkan: $e',
        backgroundColor: AppColors.orangeWarning,
      );
    }
  }

  Future<void> _toggleMic() async {
    if (!_speechReady) {
      if (!mounted) return;
      AppToast.show(
        context,
        'Speech-to-text belum tersedia. Di emulator (mis. LDPlayer) sering gagal '
        'karena tidak ada layanan pengenalan suara Google. '
        'Pasang Google Play Services / buka Play Store, atau uji di HP asli. '
        'Pastikan juga izin Mikrofon untuk aplikasi ini.',
        duration: const Duration(seconds: 6),
        backgroundColor: AppColors.orangeWarning,
      );
      return;
    }
    if (_orderType != 'walk_in') return;

    if (_micSessionActive) {
      _micSessionActive = false;
      await _speech.stop();
      if (mounted) setState(() => _listeningUi = false);
      return;
    }

    _micSessionActive = true;
    await _resumeListen();
  }

  void _processSpeechResult(String transcript) {
    final items = parseSpeechToItems(transcript);
    final added = <String>[];
    final notFound = <String>[];

    for (final item in items) {
      final product = findBestProductMatch(item.productNameLower, _products);
      if (product != null) {
        final n = item.qty;
        for (var k = 0; k < n; k++) {
          _addToCart(product);
        }
        added.add('${product.name} × $n');
      } else {
        notFound.add(item.productNameLower);
      }
    }

    if (!mounted) return;
    setState(() {});

    if (added.isNotEmpty) {
      AppToast.show(
        context,
        'Ditambahkan: ${added.join(', ')}',
        backgroundColor: AppColors.greenSuccess,
      );
    }
    if (notFound.isNotEmpty) {
      AppToast.show(
        context,
        'Tidak ditemukan: ${notFound.join(', ')}',
        backgroundColor: AppColors.redCancel,
        duration: const Duration(seconds: 4),
      );
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final auth = context.read<AuthProvider>();
      final storeId = auth.selectedStoreId;
      if (storeId == null) {
        if (mounted) setState(() => _loading = false);
        return;
      }
      final repo = auth.posRepository;
      final results = await Future.wait([
        repo.getProducts(storeId: storeId),
        repo.getCategories(),
        repo.getPaymentMethods(),
      ]);
      if (mounted) {
        setState(() {
          _products = results[0] as List<Product>? ?? [];
          _categories = results[1] as List<Category>? ?? [];
          _paymentMethods = results[2] as List<PaymentMethod>? ?? [];
          _selectedPayment = _paymentMethods.isNotEmpty ? _paymentMethods.first : null;
          _loading = false;
        });
        await _refreshStoreReceiptFlag();
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString().replaceAll('ApiException: ', '');
          _loading = false;
        });
      }
    }
  }

  Future<void> _refreshStoreReceiptFlag() async {
    final auth = context.read<AuthProvider>();
    final id = auth.selectedStoreId;
    if (id == null) return;
    try {
      final store = await auth.api.getCashierStore(storeId: id);
      if (mounted) {
        setState(() {
          _receiptPrintEnabled = store.receiptPrintEnabled;
          if (!_receiptPrintEnabled) _wantPrintReceipt = false;
        });
      }
    } catch (_) {
      final s = auth.selectedStore;
      final u = auth.user?.store;
      if (mounted) {
        setState(() {
          _receiptPrintEnabled = s?.receiptPrintEnabled ?? u?.receiptPrintEnabled ?? false;
          if (!_receiptPrintEnabled) _wantPrintReceipt = false;
        });
      }
    }
  }

  Future<void> _printReceipt({
    required String storeName,
    required Order order,
    required String paymentMethodLabel,
  }) async {
    try {
      StoreInfo? store;
      try {
        final auth = context.read<AuthProvider>();
        store = await auth.api.getCashierStore(storeId: auth.selectedStoreId);
      } catch (_) {}

      final pdfBytes = await ReceiptPdf.build(
        storeName: storeName,
        order: order,
        paymentMethodLabel: paymentMethodLabel,
        formatRp: _formatNumber,
        storeAddress: store?.address,
        storeCity: store?.city,
        storePhone: store?.phone,
      );

      // Try preview with fallback to save
      try {
        await Printing.layoutPdf(
          onLayout: (_) => pdfBytes,
          name: 'struk-${order.orderCode}.pdf',
        );
      } catch (previewError) {
        // Fallback: save to file if preview fails
        if (mounted) {
          AppToast.show(
            context,
            'Preview tidak tersedia, menyimpan file...',
            backgroundColor: AppColors.orangeWarning,
          );
        }
        
        final dir = await getApplicationDocumentsDirectory();
        final filePath = '${dir.path}/struk_${order.orderCode}.pdf';
        final file = File(filePath);
        await file.writeAsBytes(pdfBytes);
        
        if (mounted) {
          AppToast.show(
            context,
            'Struk disimpan: ${order.orderCode}',
            backgroundColor: AppColors.greenSuccess,
          );
        }
      }
    } catch (e) {
      if (mounted) {
        AppToast.show(
          context,
          'Gagal cetak struk: $e',
          backgroundColor: Colors.red,
        );
      }
    }
  }

  List<Product> get _filteredProducts {
    if (_selectedCategoryId == null) return _products;
    return _products.where((p) => p.categoryId == _selectedCategoryId).toList();
  }

  void _addToCart(Product product) {
    final existing = _cart.indexWhere((c) => c.product.id == product.id);
    if (existing >= 0) {
      _cart[existing] = CartItem(product: product, quantity: _cart[existing].quantity + 1);
    } else {
      _cart.add(CartItem(product: product, quantity: 1));
    }
    setState(() {});
  }

  void _updateCartQuantity(int index, double delta) {
    final qty = _cart[index].quantity + delta;
    if (qty <= 0) {
      _cart.removeAt(index);
    } else {
      _cart[index] = CartItem(product: _cart[index].product, quantity: qty);
    }
    setState(() {});
  }

  double get _total => _cart.fold(0, (sum, c) => sum + (c.product.sellPrice * c.quantity));

  Future<void> _checkout() async {
    if (_cart.isEmpty) {
      AppToast.show(context, 'Keranjang kosong');
      return;
    }
    if (_selectedPayment == null) {
      AppToast.show(context, 'Pilih metode pembayaran');
      return;
    }
    if (_selectedPayment!.requiresCashInput) {
      final cash = double.tryParse(_cashController.text.replaceAll(',', '').replaceAll('.', ''));
      if (cash == null || cash < _total) {
        AppToast.show(context, 'Uang tunai minimal Rp ${_formatNumber(_total)}');
        return;
      }
    }

    final auth = context.read<AuthProvider>();
    final wantPrint = _wantPrintReceipt;
    final paymentLabel = _selectedPayment?.name ?? '—';
    final storeName =
        auth.selectedStore?.name ?? auth.user?.store?.name ?? 'Toko';

    final items = _cart
        .map((c) => {'product_id': c.product.id, 'quantity': c.quantity})
        .toList();

    double? cashReceived;
    if (_selectedPayment!.requiresCashInput) {
      cashReceived = double.tryParse(_cashController.text.replaceAll(',', '').replaceAll('.', ''));
    }

    final result = await auth.posRepository.createOrder(
      storeId: auth.selectedStoreId,
      type: _orderType,
      items: items,
      paymentMethod: _selectedPayment!.code,
      cashReceived: cashReceived,
      total: _total,
    );

    if (!mounted) return;
    _cart.clear();
    _cashController.clear();
    setState(() {});

    switch (result.type) {
      case OrderResultType.success:
        AppToast.show(
          context,
          'Pesanan ${result.order!.orderCode} berhasil!',
          backgroundColor: AppColors.greenSuccess,
        );
        Navigator.pop(context);
        if (wantPrint && _receiptPrintEnabled) {
          await _printReceipt(
            storeName: storeName,
            order: result.order!,
            paymentMethodLabel: paymentLabel,
          );
        }
        break;
      case OrderResultType.offlineQueued:
        AppToast.show(
          context,
          'Pesanan ${result.localOrderCode} tersimpan (offline). Akan disinkronkan saat online.',
          backgroundColor: Colors.orange,
        );
        Navigator.pop(context);
        break;
      case OrderResultType.failed:
        AppToast.show(
          context,
          result.message ?? 'Gagal membuat pesanan',
          backgroundColor: AppColors.redCancel,
        );
        break;
    }
  }

  String _formatNumber(double value) {
    final s = value.toStringAsFixed(0);
    final buffer = StringBuffer();
    for (var i = 0; i < s.length; i++) {
      if (i > 0 && (s.length - i) % 3 == 0) buffer.write('.');
      buffer.write(s[i]);
    }
    return buffer.toString();
  }

  void _showPendingOrdersModal() async {
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _PendingOrdersModal(
        onRefresh: _loadPendingCount,
        onOrderTap: () async {
          final navigator = Navigator.of(ctx);
          navigator.pop(ctx);
          await navigator.pushNamed('/pending-orders');
          if (mounted) _loadPendingCount();
        },
      ),
    );
    if (mounted) _loadPendingCount();
  }

  void _showCartSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (ctx) {
        final mq = MediaQuery.of(ctx);
        // Tinggi tetap 90% — tanpa DraggableScrollableSheet supaya geser vertikal
        // tidak “dirampas” untuk drag resize sheet (scroll hanya jalan di pinggir).
        final sheetHeight = mq.size.height * 0.9;
        return Padding(
          padding: EdgeInsets.only(bottom: mq.viewInsets.bottom),
          child: StatefulBuilder(
            builder: (ctx, setModalState) => ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
              child: Container(
                height: sheetHeight,
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.surface,
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                ),
                child: Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.fromLTRB(12, 10, 4, 8),
                      child: Row(
                        children: [
                          const Text(
                            'Keranjang',
                            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                          ),
                          const Spacer(),
                          IconButton(
                            visualDensity: VisualDensity.compact,
                            icon: const Icon(Icons.close_rounded, size: 22),
                            onPressed: () => Navigator.pop(ctx),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: _CartPanel(
                        cart: _cart,
                        total: _total,
                        selectedPayment: _selectedPayment,
                        paymentMethods: _paymentMethods,
                        cashController: _cashController,
                        mobileSheet: true,
                        showCartHeader: false,
                        receiptPrintEnabled: _receiptPrintEnabled,
                        wantPrintReceipt: _wantPrintReceipt,
                        onWantPrintReceiptChanged: (v) {
                          setState(() => _wantPrintReceipt = v);
                          setModalState(() {});
                        },
                        onPaymentChanged: (v) {
                          setState(() => _selectedPayment = v);
                          setModalState(() {});
                        },
                        onUpdateQuantity: (i, d) {
                          _updateCartQuantity(i, d);
                          setModalState(() {});
                        },
                        onCheckout: _checkout,
                        formatNumber: _formatNumber,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;
    final isWide = width >= 600;

    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Scaffold(
      resizeToAvoidBottomInset: true,
      body: _loading
          ? Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const SizedBox(
                    width: 28,
                    height: 28,
                    child: CircularProgressIndicator(strokeWidth: 2.5),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'Memuat produk...',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            )
          : _error != null
              ? _ErrorState(message: _error!, onRetry: _load)
              : Column(
                  children: [
                    _PosTopBar(
                      orderType: _orderType,
                      onOrderTypeChanged: (v) async {
                        if (v != 'walk_in' && _micSessionActive) {
                          _micSessionActive = false;
                          if (_speech.isListening) {
                            await _speech.stop();
                          }
                          if (mounted) setState(() => _listeningUi = false);
                        }
                        if (mounted) setState(() => _orderType = v);
                      },
                      onBack: () => Navigator.pop(context),
                      cartCount: _cart.length,
                      onCartTap: isWide ? null : () => _showCartSheet(context),
                      pendingCount: _pendingOrdersCount,
                      onBellTap: _showPendingOrdersModal,
                      showSpeechMic: _orderType == 'walk_in',
                      speechListening: _listeningUi,
                      speechAvailable: _speechReady,
                      onSpeechMicTap: _toggleMic,
                    ),
                    Expanded(
                      child: isWide
                          ? _WideLayout(
                              products: _filteredProducts,
                              categories: _categories,
                              selectedCategoryId: _selectedCategoryId,
                              onCategoryTap: (id) => setState(() => _selectedCategoryId = id),
                              cart: _cart,
                              total: _total,
                              selectedPayment: _selectedPayment,
                              paymentMethods: _paymentMethods,
                              cashController: _cashController,
                              onPaymentChanged: (v) => setState(() => _selectedPayment = v),
                              onUpdateQuantity: _updateCartQuantity,
                              onCheckout: _checkout,
                              onAddToCart: _addToCart,
                              formatNumber: _formatNumber,
                              receiptPrintEnabled: _receiptPrintEnabled,
                              wantPrintReceipt: _wantPrintReceipt,
                              onWantPrintReceiptChanged: (v) =>
                                  setState(() => _wantPrintReceipt = v),
                            )
                          : _NarrowLayout(
                              products: _filteredProducts,
                              categories: _categories,
                              selectedCategoryId: _selectedCategoryId,
                              onCategoryTap: (id) => setState(() => _selectedCategoryId = id),
                              onAddToCart: _addToCart,
                            ),
                    ),
                    if (!isWide) _MobileFooter(total: _total, cartCount: _cart.length, onTap: () => _showCartSheet(context)),
                  ],
                ),
    );
  }
}

class _PosTopBar extends StatelessWidget {
  final String orderType;
  final ValueChanged<String> onOrderTypeChanged;
  final VoidCallback onBack;
  final int cartCount;
  final VoidCallback? onCartTap;
  final int pendingCount;
  final VoidCallback? onBellTap;
  final bool showSpeechMic;
  final bool speechListening;
  final bool speechAvailable;
  final VoidCallback? onSpeechMicTap;

  const _PosTopBar({
    required this.orderType,
    required this.onOrderTypeChanged,
    required this.onBack,
    required this.cartCount,
    this.onCartTap,
    this.pendingCount = 0,
    this.onBellTap,
    this.showSpeechMic = false,
    this.speechListening = false,
    this.speechAvailable = false,
    this.onSpeechMicTap,
  });

  @override
  Widget build(BuildContext context) {
    final w = MediaQuery.sizeOf(context).width;
    final isCompact = w < 420;

    return Container(
      padding: EdgeInsets.fromLTRB(4, isCompact ? 5 : 6, 8, isCompact ? 5 : 6),
      decoration: const BoxDecoration(
        color: AppColors.bluePrimary,
        boxShadow: [
          BoxShadow(color: Colors.black26, blurRadius: 6, offset: Offset(0, 1)),
        ],
      ),
      child: SafeArea(
        bottom: false,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            IconButton(
              visualDensity: VisualDensity.compact,
              constraints: const BoxConstraints(minWidth: 40, minHeight: 40),
              padding: EdgeInsets.zero,
              icon: const Icon(Icons.arrow_back_rounded, color: Colors.white, size: 22),
              onPressed: onBack,
            ),
            const SizedBox(width: 4),
            Expanded(
              child: _PosOrderTypeToggle(
                orderType: orderType,
                onChanged: onOrderTypeChanged,
                compact: isCompact,
              ),
            ),
            if (showSpeechMic && onSpeechMicTap != null) ...[
              const SizedBox(width: 2),
              Tooltip(
                message: !speechAvailable
                    ? 'Speech belum siap — ketuk untuk penjelasan (emulator sering perlu Google Play)'
                    : (speechListening
                        ? 'Mendengarkan… Ketuk lagi untuk berhenti'
                        : 'Ucapkan nama produk (Speech-to-Text)'),
                child: IconButton(
                  visualDensity: VisualDensity.compact,
                  constraints: const BoxConstraints(minWidth: 40, minHeight: 40),
                  padding: EdgeInsets.zero,
                  // Tetap bisa diketuk walau speech gagal init — supaya user dapat toast penjelasan.
                  onPressed: onSpeechMicTap,
                  icon: Icon(
                    speechListening ? Icons.mic_rounded : Icons.mic_none_rounded,
                    color: speechListening
                        ? Colors.white
                        : (speechAvailable ? Colors.white.withValues(alpha: 0.95) : Colors.white54),
                    size: 22,
                  ),
                ),
              ),
            ],
            if (onBellTap != null) ...[
              Stack(
                clipBehavior: Clip.none,
                children: [
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    constraints: const BoxConstraints(minWidth: 40, minHeight: 40),
                    padding: EdgeInsets.zero,
                    icon: const Icon(Icons.notifications_rounded, color: Colors.white, size: 22),
                    onPressed: onBellTap,
                    tooltip: 'Pesanan masuk',
                  ),
                  if (pendingCount > 0)
                    Positioned(
                      right: 2,
                      top: 2,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                        decoration: const BoxDecoration(color: AppColors.redCancel, shape: BoxShape.circle),
                        constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                        child: Text(
                          pendingCount > 99 ? '99+' : '$pendingCount',
                          style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ),
                ],
              ),
            ],
            if (onCartTap != null) ...[
              if (onBellTap != null) const SizedBox(width: 4),
              Stack(
                clipBehavior: Clip.none,
                children: [
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    constraints: const BoxConstraints(minWidth: 40, minHeight: 40),
                    padding: EdgeInsets.zero,
                    icon: const Icon(Icons.shopping_cart_rounded, color: Colors.white, size: 22),
                    onPressed: onCartTap,
                    tooltip: 'Keranjang',
                  ),
                  if (cartCount > 0)
                    Positioned(
                      right: 2,
                      top: 2,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                        decoration: const BoxDecoration(color: AppColors.redCancel, shape: BoxShape.circle),
                        constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                        child: Text(
                          cartCount > 99 ? '99+' : '$cartCount',
                          style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Toggle satu baris — lebih ringkas dari [SegmentedButton] di layar HP (tidak memaksa tinggi/wrap).
class _PosOrderTypeToggle extends StatelessWidget {
  final String orderType;
  final ValueChanged<String> onChanged;
  final bool compact;

  const _PosOrderTypeToggle({
    required this.orderType,
    required this.onChanged,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    final h = compact ? 32.0 : 36.0;
    final fontSize = compact ? 11.0 : 12.5;
    final iconSize = compact ? 14.0 : 16.0;

    Widget seg(String value, String label, IconData icon, {String? tooltip}) {
      final sel = orderType == value;
      return Expanded(
        child: Tooltip(
          message: tooltip ?? label,
          child: Material(
            color: sel ? Colors.white : Colors.transparent,
            borderRadius: BorderRadius.circular(10),
            child: InkWell(
              onTap: () => onChanged(value),
              borderRadius: BorderRadius.circular(10),
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: compact ? 4 : 8, vertical: compact ? 4 : 6),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      icon,
                      size: iconSize,
                      color: sel ? AppColors.bluePrimary : Colors.white,
                    ),
                    SizedBox(width: compact ? 4 : 6),
                    Flexible(
                      child: Text(
                        label,
                        maxLines: 1,
                        softWrap: false,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: fontSize,
                          fontWeight: FontWeight.w600,
                          color: sel ? AppColors.bluePrimary : Colors.white,
                          height: 1.1,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
    }

    return SizedBox(
      height: h,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: Colors.white24,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          children: [
            seg('walk_in', 'Walk-in', Icons.person_rounded, tooltip: 'Pesanan di tempat'),
            Container(width: 1, height: h - 8, color: Colors.white30),
            seg('takeaway', compact ? 'Bungkus' : 'Bawa Pulang', Icons.shopping_bag_rounded, tooltip: 'Bawa pulang / takeaway'),
          ],
        ),
      ),
    );
  }
}

class _WideLayout extends StatelessWidget {
  final List<Product> products;
  final List<Category> categories;
  final int? selectedCategoryId;
  final ValueChanged<int?> onCategoryTap;
  final List<CartItem> cart;
  final double total;
  final PaymentMethod? selectedPayment;
  final List<PaymentMethod> paymentMethods;
  final TextEditingController cashController;
  final ValueChanged<PaymentMethod?> onPaymentChanged;
  final void Function(int, double) onUpdateQuantity;
  final VoidCallback onCheckout;
  final void Function(Product) onAddToCart;
  final String Function(double) formatNumber;
  final bool receiptPrintEnabled;
  final bool wantPrintReceipt;
  final ValueChanged<bool> onWantPrintReceiptChanged;

  const _WideLayout({
    required this.products,
    required this.categories,
    required this.selectedCategoryId,
    required this.onCategoryTap,
    required this.cart,
    required this.total,
    required this.selectedPayment,
    required this.paymentMethods,
    required this.cashController,
    required this.onPaymentChanged,
    required this.onUpdateQuantity,
    required this.onCheckout,
    required this.onAddToCart,
    required this.formatNumber,
    required this.receiptPrintEnabled,
    required this.wantPrintReceipt,
    required this.onWantPrintReceiptChanged,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Row(
      children: [
        // Keranjang (kiri - seperti referensi)
        Container(
          width: 320,
          decoration: BoxDecoration(
            color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
            border: Border(right: BorderSide(color: colorScheme.outlineVariant.withValues(alpha: 0.5))),
          ),
          child: _CartPanel(
            cart: cart,
            total: total,
            selectedPayment: selectedPayment,
            paymentMethods: paymentMethods,
            cashController: cashController,
            onPaymentChanged: onPaymentChanged,
            onUpdateQuantity: onUpdateQuantity,
            onCheckout: onCheckout,
            formatNumber: formatNumber,
            showCartHeader: true,
            receiptPrintEnabled: receiptPrintEnabled,
            wantPrintReceipt: wantPrintReceipt,
            onWantPrintReceiptChanged: onWantPrintReceiptChanged,
          ),
        ),
        // Produk (kanan)
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (categories.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
                  child: SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        _CategoryChip(
                          label: 'Semua',
                          isSelected: selectedCategoryId == null,
                          onTap: () => onCategoryTap(null),
                        ),
                        ...categories.map((c) => _CategoryChip(
                              label: c.name,
                              isSelected: selectedCategoryId == c.id,
                              onTap: () => onCategoryTap(c.id),
                            )),
                      ],
                    ),
                  ),
                ),
              Expanded(
                child: GridView.builder(
                  padding: const EdgeInsets.all(12),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 5,
                    childAspectRatio: 0.85,
                    crossAxisSpacing: 10,
                    mainAxisSpacing: 10,
                  ),
                  itemCount: products.length,
                  itemBuilder: (_, i) => _ProductCard(product: products[i], onTap: () => onAddToCart(products[i])),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _NarrowLayout extends StatelessWidget {
  final List<Product> products;
  final List<Category> categories;
  final int? selectedCategoryId;
  final ValueChanged<int?> onCategoryTap;
  final void Function(Product) onAddToCart;

  const _NarrowLayout({
    required this.products,
    required this.categories,
    required this.selectedCategoryId,
    required this.onCategoryTap,
    required this.onAddToCart,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (categories.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  _CategoryChip(
                    label: 'Semua',
                    isSelected: selectedCategoryId == null,
                    onTap: () => onCategoryTap(null),
                  ),
                  ...categories.map((c) => _CategoryChip(
                        label: c.name,
                        isSelected: selectedCategoryId == c.id,
                        onTap: () => onCategoryTap(c.id),
                      )),
                ],
              ),
            ),
          ),
        Expanded(
          child: GridView.builder(
            padding: const EdgeInsets.all(12),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 3,
              childAspectRatio: 0.85,
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
            ),
            itemCount: products.length,
            itemBuilder: (_, i) => _ProductCard(product: products[i], onTap: () => onAddToCart(products[i])),
          ),
        ),
      ],
    );
  }
}

class _CategoryChip extends StatelessWidget {
  final String label;
  final bool isSelected;
  final VoidCallback onTap;

  const _CategoryChip({required this.label, required this.isSelected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 6),
      child: FilterChip(
        label: Text(label),
        selected: isSelected,
        onSelected: (_) => onTap(),
        selectedColor: AppColors.bluePrimary,
        checkmarkColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        labelStyle: TextStyle(
          color: isSelected ? Colors.white : null,
          fontWeight: isSelected ? FontWeight.w600 : FontWeight.w500,
          fontSize: 12,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(8),
          side: BorderSide(
            color: isSelected ? AppColors.bluePrimary : Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.3),
          ),
        ),
      ),
    );
  }
}

class _MobileFooter extends StatelessWidget {
  final double total;
  final int cartCount;
  final VoidCallback onTap;

  const _MobileFooter({required this.total, required this.cartCount, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.greenSuccess,
      elevation: 8,
      child: SafeArea(
        top: false,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: Row(
              children: [
                const Icon(Icons.shopping_cart_rounded, color: Colors.white, size: 24),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        '$cartCount item',
                        style: const TextStyle(color: Colors.white70, fontSize: 11),
                      ),
                      Text(
                        'Rp ${_formatNumber(total)}',
                        style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.arrow_forward_ios_rounded, color: Colors.white, size: 16),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _formatNumber(double value) {
    final s = value.toStringAsFixed(0);
    final buffer = StringBuffer();
    for (var i = 0; i < s.length; i++) {
      if (i > 0 && (s.length - i) % 3 == 0) buffer.write('.');
      buffer.write(s[i]);
    }
    return buffer.toString();
  }
}

class _ProductCard extends StatelessWidget {
  final Product product;
  final VoidCallback onTap;

  const _ProductCard({required this.product, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.all(6),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: colorScheme.outlineVariant.withValues(alpha: 0.3)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.06),
                blurRadius: 4,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(6),
                  child: product.imageUrl != null
                      ? Image.network(
                          product.imageUrl!,
                          fit: BoxFit.cover,
                          width: double.infinity,
                          errorBuilder: (_, __, ___) => _placeholder(colorScheme),
                        )
                      : _placeholder(colorScheme),
                ),
              ),
              const SizedBox(height: 4),
              Text(
                product.name,
                style: theme.textTheme.bodySmall?.copyWith(
                  fontWeight: FontWeight.w500,
                  fontSize: 11,
                  letterSpacing: 0,
                  height: 1.2,
                ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: AppColors.bluePrimary.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  'Rp ${_formatPrice(product.sellPrice)}',
                  style: theme.textTheme.labelSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: AppColors.bluePrimary,
                    fontSize: 11,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _placeholder(ColorScheme cs) => Container(
        color: cs.surfaceContainerHighest.withValues(alpha: 0.3),
        child: Center(
          child: Icon(
            Icons.inventory_2_rounded,
            size: 28,
            color: cs.onSurfaceVariant.withValues(alpha: 0.4),
          ),
        ),
      );

  String _formatPrice(double v) {
    final s = v.toStringAsFixed(0);
    final b = StringBuffer();
    for (var i = 0; i < s.length; i++) {
      if (i > 0 && (s.length - i) % 3 == 0) b.write('.');
      b.write(s[i]);
    }
    return b.toString();
  }
}

class _CartPanel extends StatefulWidget {
  final List<CartItem> cart;
  final double total;
  final PaymentMethod? selectedPayment;
  final List<PaymentMethod> paymentMethods;
  final TextEditingController cashController;
  final ValueChanged<PaymentMethod?> onPaymentChanged;
  final void Function(int, double) onUpdateQuantity;
  final VoidCallback onCheckout;
  final String Function(double) formatNumber;
  /// `true` = bottom sheet HP (sticky tombol). Tanpa [DraggableScrollableSheet] agar scroll
  /// di area item/card tidak bentrok dengan gesture drag resize sheet.
  final bool mobileSheet;
  final bool showCartHeader;
  final bool receiptPrintEnabled;
  final bool wantPrintReceipt;
  final ValueChanged<bool> onWantPrintReceiptChanged;

  const _CartPanel({
    required this.cart,
    required this.total,
    required this.selectedPayment,
    required this.paymentMethods,
    required this.cashController,
    required this.onPaymentChanged,
    required this.onUpdateQuantity,
    required this.onCheckout,
    required this.formatNumber,
    this.mobileSheet = false,
    this.showCartHeader = true,
    this.receiptPrintEnabled = false,
    this.wantPrintReceipt = false,
    required this.onWantPrintReceiptChanged,
  });

  @override
  State<_CartPanel> createState() => _CartPanelState();
}

class _CartPanelState extends State<_CartPanel> {
  static double? _parseCashInput(String text) {
    final cleaned = text.replaceAll(',', '').replaceAll('.', '');
    if (cleaned.isEmpty) return null;
    return double.tryParse(cleaned);
  }

  @override
  void initState() {
    super.initState();
    widget.cashController.addListener(_onCashChanged);
  }

  @override
  void dispose() {
    widget.cashController.removeListener(_onCashChanged);
    super.dispose();
  }

  void _onCashChanged() {
    if (mounted) setState(() {});
  }

  Widget _buildCartHeaderBar(ThemeData theme, ColorScheme colorScheme, List<CartItem> cart) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
        border: Border(bottom: BorderSide(color: colorScheme.outlineVariant.withValues(alpha: 0.3))),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: AppColors.bluePrimary.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(Icons.shopping_cart_rounded, color: AppColors.bluePrimary, size: 18),
          ),
          const SizedBox(width: 8),
          Text(
            'Keranjang',
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w800,
              fontSize: 14,
            ),
          ),
          const Spacer(),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(
              color: AppColors.bluePrimary.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: AppColors.bluePrimary.withValues(alpha: 0.3)),
            ),
            child: Text(
              '${cart.length} item',
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                color: AppColors.bluePrimary,
                fontSize: 11,
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// Metode bayar + total + kembalian (ikut scroll di modal HP).
  Widget _buildCheckoutPaymentBlock(
    BuildContext context,
    ThemeData theme,
    ColorScheme colorScheme, {
    required double total,
    required PaymentMethod? selectedPayment,
    required List<PaymentMethod> paymentMethods,
    required TextEditingController cashController,
    required String Function(double) formatNumber,
    required bool needsCash,
    required double? kembalian,
    required double? kurang,
  }) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (widget.receiptPrintEnabled) ...[
          CheckboxListTile(
            value: widget.wantPrintReceipt,
            onChanged: (v) => widget.onWantPrintReceiptChanged(v ?? false),
            title: Text(
              'Cetak struk setelah bayar',
              style: theme.textTheme.bodyMedium?.copyWith(fontSize: 13),
            ),
            dense: true,
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
          ),
          const SizedBox(height: 4),
        ],
        DropdownButtonFormField<PaymentMethod>(
          value: selectedPayment,
          decoration: InputDecoration(
            labelText: 'Metode Pembayaran',
            labelStyle: const TextStyle(fontSize: 12),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
            isDense: true,
            contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
            filled: true,
            fillColor: colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
          ),
          items: paymentMethods
              .map((m) => DropdownMenuItem(value: m, child: Text(m.name, style: const TextStyle(fontSize: 13))))
              .toList(),
          onChanged: widget.onPaymentChanged,
          style: theme.textTheme.bodyMedium?.copyWith(fontSize: 13),
        ),
        if (needsCash) ...[
          const SizedBox(height: 8),
          TextFormField(
            controller: cashController,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[\d.,]'))],
            style: theme.textTheme.bodyMedium?.copyWith(fontSize: 13),
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(
              isDense: true,
              labelText: 'Uang Diterima',
              labelStyle: const TextStyle(fontSize: 12),
              hintText: 'Contoh: 50000 atau 50.000',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              filled: true,
              fillColor: colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
            ),
          ),
          if (kurang != null) ...[
            const SizedBox(height: 10),
            Row(
              children: [
                Icon(Icons.warning_amber_rounded, color: AppColors.orangeWarning, size: 18),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Uang masih kurang Rp ${formatNumber(kurang)}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.orangeWarning,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
        const SizedBox(height: 10),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
          decoration: BoxDecoration(
            color: AppColors.greenSuccess.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: AppColors.greenSuccess.withValues(alpha: 0.2)),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Total',
                style: theme.textTheme.titleSmall?.copyWith(
                  color: colorScheme.onSurfaceVariant,
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                ),
              ),
              Text(
                'Rp ${formatNumber(total)}',
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: AppColors.greenSuccess,
                  fontSize: 16,
                ),
              ),
            ],
          ),
        ),
        if (needsCash && kembalian != null && kembalian >= 0) ...[
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
            decoration: BoxDecoration(
              color: AppColors.bluePrimary.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(
                color: AppColors.bluePrimary.withValues(alpha: 0.25),
              ),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Kembalian',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: colorScheme.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                  ),
                ),
                Text(
                  'Rp ${formatNumber(kembalian)}',
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: AppColors.bluePrimary,
                    fontSize: 15,
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildCheckoutProcessButton(
    ThemeData theme,
    ColorScheme colorScheme, {
    required List<CartItem> cart,
  }) {
    return FilledButton.icon(
      onPressed: cart.isEmpty ? null : widget.onCheckout,
      style: FilledButton.styleFrom(
        backgroundColor: AppColors.greenSuccess,
        disabledBackgroundColor: colorScheme.surfaceContainerHighest,
        padding: const EdgeInsets.symmetric(vertical: 11),
        visualDensity: VisualDensity.compact,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
      icon: const Icon(Icons.payment_rounded, size: 18),
      label: const Text(
        'Proses Pembayaran',
        style: TextStyle(
          fontWeight: FontWeight.w800,
          fontSize: 13,
        ),
      ),
    );
  }

  /// Tombol checkout tetap di bawah modal HP (tidak ikut scroll).
  Widget _buildCheckoutStickyButtonBar(
    ThemeData theme,
    ColorScheme colorScheme, {
    required List<CartItem> cart,
    required double bottomInset,
  }) {
    return Material(
      color: colorScheme.surface,
      elevation: 6,
      shadowColor: Colors.black.withValues(alpha: 0.12),
      child: Container(
        width: double.infinity,
        padding: EdgeInsets.fromLTRB(12, 8, 12, 8 + bottomInset),
        decoration: BoxDecoration(
          border: Border(top: BorderSide(color: colorScheme.outlineVariant.withValues(alpha: 0.35), width: 1)),
        ),
        child: _buildCheckoutProcessButton(theme, colorScheme, cart: cart),
      ),
    );
  }

  /// Tablet / sidebar: ringkasan + tombol dalam satu blok (scroll di ListView induk).
  Widget _buildCheckoutFooter(
    BuildContext context,
    ThemeData theme,
    ColorScheme colorScheme, {
    required double total,
    required PaymentMethod? selectedPayment,
    required List<PaymentMethod> paymentMethods,
    required TextEditingController cashController,
    required String Function(double) formatNumber,
    required bool needsCash,
    required double? kembalian,
    required double? kurang,
    required List<CartItem> cart,
    required double bottomInset,
  }) {
    return Container(
      padding: EdgeInsets.fromLTRB(12, 10, 12, 10 + bottomInset),
      decoration: BoxDecoration(
        color: colorScheme.surface,
        border: Border(top: BorderSide(color: colorScheme.outlineVariant.withValues(alpha: 0.3), width: 1.5)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 8,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _buildCheckoutPaymentBlock(
            context,
            theme,
            colorScheme,
            total: total,
            selectedPayment: selectedPayment,
            paymentMethods: paymentMethods,
            cashController: cashController,
            formatNumber: formatNumber,
            needsCash: needsCash,
            kembalian: kembalian,
            kurang: kurang,
          ),
          const SizedBox(height: 10),
          _buildCheckoutProcessButton(theme, colorScheme, cart: cart),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final cart = widget.cart;
    final total = widget.total;
    final selectedPayment = widget.selectedPayment;
    final paymentMethods = widget.paymentMethods;
    final cashController = widget.cashController;
    final formatNumber = widget.formatNumber;

    final needsCash = selectedPayment?.requiresCashInput == true;
    final cashParsed = needsCash ? _parseCashInput(cashController.text) : null;
    final kembalian =
        (cashParsed != null && cashParsed >= total) ? (cashParsed - total) : null;
    final kurang = (cashParsed != null && cashParsed < total && cashParsed > 0)
        ? (total - cashParsed)
        : null;

    final viewInsets = MediaQuery.of(context).viewInsets.bottom;
    final safeBottom = MediaQuery.of(context).padding.bottom;
    final bottomInset = viewInsets + safeBottom;

    if (widget.mobileSheet) {
      final paymentScroll = Container(
        width: double.infinity,
        decoration: BoxDecoration(
          color: colorScheme.surface,
          border: Border(
            top: BorderSide(color: colorScheme.outlineVariant.withValues(alpha: 0.3), width: 1.5),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 6,
              offset: const Offset(0, -2),
            ),
          ],
        ),
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
        child: _buildCheckoutPaymentBlock(
          context,
          theme,
          colorScheme,
          total: total,
          selectedPayment: selectedPayment,
          paymentMethods: paymentMethods,
          cashController: cashController,
          formatNumber: formatNumber,
          needsCash: needsCash,
          kembalian: kembalian,
          kurang: kurang,
        ),
      );

      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: CustomScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              slivers: [
                if (widget.showCartHeader)
                  SliverToBoxAdapter(child: _buildCartHeaderBar(theme, colorScheme, cart)),
                if (cart.isEmpty)
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
                              shape: BoxShape.circle,
                            ),
                            child: Icon(
                              Icons.shopping_cart_outlined,
                              size: 36,
                              color: colorScheme.onSurfaceVariant.withValues(alpha: 0.4),
                            ),
                          ),
                          const SizedBox(height: 12),
                          Text(
                            'Keranjang Kosong',
                            style: theme.textTheme.titleSmall?.copyWith(
                              color: colorScheme.onSurfaceVariant,
                              fontWeight: FontWeight.w700,
                              fontSize: 15,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Tambahkan produk untuk melanjutkan',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: colorScheme.onSurfaceVariant.withValues(alpha: 0.7),
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                  )
                else
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
                    sliver: SliverList(
                      delegate: SliverChildBuilderDelegate(
                        (context, i) => Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: _CartItemCard(
                            item: cart[i],
                            index: i,
                            onUpdate: widget.onUpdateQuantity,
                          ),
                        ),
                        childCount: cart.length,
                      ),
                    ),
                  ),
                SliverToBoxAdapter(child: paymentScroll),
              ],
            ),
          ),
          _buildCheckoutStickyButtonBar(
            theme,
            colorScheme,
            cart: cart,
            bottomInset: bottomInset,
          ),
        ],
      );
    }

    final checkoutFooter = _buildCheckoutFooter(
      context,
      theme,
      colorScheme,
      total: total,
      selectedPayment: selectedPayment,
      paymentMethods: paymentMethods,
      cashController: cashController,
      formatNumber: formatNumber,
      needsCash: needsCash,
      kembalian: kembalian,
      kurang: kurang,
      cart: cart,
      bottomInset: bottomInset,
    );

    // Tablet / sidebar: footer harus ikut scroll supaya tidak RenderFlex overflow
    // saat metode tunai (field uang + kembalian) menambah tinggi footer.
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (widget.showCartHeader) _buildCartHeaderBar(theme, colorScheme, cart),
        Expanded(
          child: ListView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            children: [
              ...cart.isEmpty
                  ? <Widget>[
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 20),
                        child: Center(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Container(
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
                                  shape: BoxShape.circle,
                                ),
                                child: Icon(
                                  Icons.shopping_cart_outlined,
                                  size: 36,
                                  color: colorScheme.onSurfaceVariant.withValues(alpha: 0.4),
                                ),
                              ),
                              const SizedBox(height: 12),
                              Text(
                                'Keranjang Kosong',
                                style: theme.textTheme.titleSmall?.copyWith(
                                  color: colorScheme.onSurfaceVariant,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 15,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Tambahkan produk untuk melanjutkan',
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: colorScheme.onSurfaceVariant.withValues(alpha: 0.7),
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ]
                  : List.generate(
                      cart.length,
                      (i) => Padding(
                        padding: EdgeInsets.fromLTRB(12, i == 0 ? 8 : 0, 12, 6),
                        child: _CartItemCard(
                          item: cart[i],
                          index: i,
                          onUpdate: widget.onUpdateQuantity,
                        ),
                      ),
                    ),
              checkoutFooter,
            ],
          ),
        ),
      ],
    );
  }
}

class _CartItemCard extends StatelessWidget {
  final CartItem item;
  final int index;
  final void Function(int, double) onUpdate;

  const _CartItemCard({required this.item, required this.index, required this.onUpdate});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final c = item;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: theme.colorScheme.outlineVariant.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  c.product.name,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  'Rp ${_fp(c.product.sellPrice * c.quantity)}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.bluePrimary,
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Container(
            decoration: BoxDecoration(
              color: theme.colorScheme.surface,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: theme.colorScheme.outlineVariant.withValues(alpha: 0.3)),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                IconButton(
                  onPressed: () => onUpdate(index, -1),
                  icon: const Icon(Icons.remove_rounded, size: 16),
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
                  style: IconButton.styleFrom(
                    backgroundColor: Colors.transparent,
                    foregroundColor: theme.colorScheme.error,
                  ),
                ),
                Container(
                  width: 1,
                  height: 20,
                  color: theme.colorScheme.outlineVariant.withValues(alpha: 0.3),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  child: Text(
                    '${c.quantity.toInt()}',
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                    ),
                  ),
                ),
                Container(
                  width: 1,
                  height: 20,
                  color: theme.colorScheme.outlineVariant.withValues(alpha: 0.3),
                ),
                IconButton(
                  onPressed: () => onUpdate(index, 1),
                  icon: const Icon(Icons.add_rounded, size: 16),
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
                  style: IconButton.styleFrom(
                    backgroundColor: Colors.transparent,
                    foregroundColor: AppColors.bluePrimary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _fp(double v) {
    final s = v.toStringAsFixed(0);
    final b = StringBuffer();
    for (var i = 0; i < s.length; i++) {
      if (i > 0 && (s.length - i) % 3 == 0) b.write('.');
      b.write(s[i]);
    }
    return b.toString();
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.error_outline_rounded, size: 48, color: colorScheme.error),
          const SizedBox(height: 14),
          Text(
            'Gagal Memuat',
            style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 6),
          Text(
            message,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodySmall?.copyWith(
              color: colorScheme.onSurfaceVariant,
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 18),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded, size: 18),
            label: const Text('Coba Lagi', style: TextStyle(fontSize: 13)),
            style: FilledButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              visualDensity: VisualDensity.compact,
            ),
          ),
        ],
      ),
    );
  }
}

/// Modal pesanan masuk: daftar pesanan + denah meja.
class _PendingOrdersModal extends StatefulWidget {
  final VoidCallback onRefresh;
  final VoidCallback onOrderTap;

  const _PendingOrdersModal({required this.onRefresh, required this.onOrderTap});

  @override
  State<_PendingOrdersModal> createState() => _PendingOrdersModalState();
}

class _PendingOrdersModalState extends State<_PendingOrdersModal> {
  List<Order> _orders = [];
  Map<String, dynamic>? _floorPlan;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final auth = context.read<AuthProvider>();
      final storeId = auth.selectedStoreId;
      if (storeId == null) {
        if (mounted) setState(() => _loading = false);
        return;
      }
      final results = await Future.wait([
        auth.api.getPendingOrders(storeId: storeId),
        auth.api.getFloorPlan(storeId: storeId),
      ]);
      if (mounted) {
        setState(() {
          _orders = results[0] as List<Order>;
          _floorPlan = results[1] as Map<String, dynamic>;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString().replaceAll('ApiException: ', '');
          _loading = false;
        });
      }
    }
  }

  String _formatCurrency(double v) {
    final s = v.toStringAsFixed(0);
    final b = StringBuffer();
    for (var i = 0; i < s.length; i++) {
      if (i > 0 && (s.length - i) % 3 == 0) b.write('.');
      b.write(s[i]);
    }
    return 'Rp $b';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Container(
      constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.78),
      decoration: BoxDecoration(
        color: colorScheme.surface,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.2), blurRadius: 16, offset: const Offset(0, -3)),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Handle bar
          Padding(
            padding: const EdgeInsets.only(top: 8, bottom: 4),
            child: Container(
              width: 36,
              height: 3,
              decoration: BoxDecoration(
                color: colorScheme.outlineVariant,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          // Header
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 8, 8),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: AppColors.orangeWarning.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.notifications_active_rounded, color: AppColors.orangeWarning, size: 22),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Pesanan Masuk',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          fontSize: 17,
                        ),
                      ),
                      Text(
                        _orders.isEmpty ? 'Tidak ada pesanan menunggu' : '${_orders.length} pesanan dari meja',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: colorScheme.onSurfaceVariant,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  visualDensity: VisualDensity.compact,
                  icon: const Icon(Icons.close_rounded, size: 22),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          // Content
          Flexible(
            child: _loading
                ? const Padding(
                    padding: EdgeInsets.all(36),
                    child: Center(
                      child: SizedBox(
                        width: 28,
                        height: 28,
                        child: CircularProgressIndicator(strokeWidth: 2.5),
                      ),
                    ),
                  )
                : _error != null
                    ? Padding(
                        padding: const EdgeInsets.all(20),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.error_outline_rounded, size: 40, color: colorScheme.error),
                            const SizedBox(height: 10),
                            Text(
                              _error!,
                              textAlign: TextAlign.center,
                              style: theme.textTheme.bodySmall?.copyWith(fontSize: 13),
                            ),
                            const SizedBox(height: 14),
                            FilledButton.icon(
                              onPressed: _load,
                              icon: const Icon(Icons.refresh_rounded, size: 17),
                              label: const Text('Coba Lagi', style: TextStyle(fontSize: 13)),
                              style: FilledButton.styleFrom(
                                visualDensity: VisualDensity.compact,
                              ),
                            ),
                          ],
                        ),
                      )
                    : SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Daftar pesanan
                            if (_orders.isNotEmpty) ...[
                              Text(
                                'Pesanan dari Meja',
                                style: theme.textTheme.titleSmall?.copyWith(
                                  fontWeight: FontWeight.w700,
                                  color: colorScheme.onSurfaceVariant,
                                  fontSize: 13,
                                ),
                              ),
                              const SizedBox(height: 8),
                              ..._orders.map((o) => _OrderTile(
                                    order: o,
                                    formatCurrency: _formatCurrency,
                                    onTap: widget.onOrderTap,
                                  )),
                              const SizedBox(height: 16),
                            ],
                            // Denah meja
                            if (_floorPlan != null && (_floorPlan!['floors'] as List).isNotEmpty) ...[
                              Text(
                                'Denah Meja',
                                style: theme.textTheme.titleSmall?.copyWith(
                                  fontWeight: FontWeight.w700,
                                  color: colorScheme.onSurfaceVariant,
                                  fontSize: 13,
                                ),
                              ),
                              const SizedBox(height: 8),
                              _FloorPlanPreview(
                                floors: _floorPlan!['floors'] as List,
                                pendingTableIds: _orders
                                    .where((o) => o.table != null)
                                    .map((o) => o.table!.id)
                                    .toSet()
                                    .toList(),
                              ),
                            ],
                            const SizedBox(height: 12),
                            SizedBox(
                              width: double.infinity,
                              child: FilledButton.icon(
                                onPressed: () {
                                  Navigator.pop(context);
                                  widget.onOrderTap();
                                },
                                icon: const Icon(Icons.receipt_long_rounded, size: 18),
                                label: Text(
                                  _orders.isEmpty ? 'Lihat Pesanan' : 'Proses ${_orders.length} Pesanan',
                                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                                ),
                                style: FilledButton.styleFrom(
                                  backgroundColor: AppColors.greenSuccess,
                                  padding: const EdgeInsets.symmetric(vertical: 11),
                                  visualDensity: VisualDensity.compact,
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}

class _OrderTile extends StatelessWidget {
  final Order order;
  final String Function(double) formatCurrency;
  final VoidCallback onTap;

  const _OrderTile({required this.order, required this.formatCurrency, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tableName = order.table?.name ?? '—';

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(10),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
            decoration: BoxDecoration(
              color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4)),
            ),
            child: Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: AppColors.bluePrimary.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Center(
                    child: Text(
                      tableName,
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: AppColors.bluePrimary,
                        fontSize: 13,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        order.orderCode,
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                          fontSize: 14,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${order.items.length} item • ${formatCurrency(order.finalAmount)}',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
                Icon(Icons.arrow_forward_ios_rounded, size: 13, color: theme.colorScheme.onSurfaceVariant),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _FloorPlanPreview extends StatelessWidget {
  final List floors;
  final List<int> pendingTableIds;

  const _FloorPlanPreview({required this.floors, required this.pendingTableIds});

  /// Skala meter → piksel agar seluruh konten (lantai + meja) muat tanpa menumpuk karena clamp ukuran.
  static const double _edgePad = 14.0;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final screenWidth = MediaQuery.of(context).size.width;
    final screenHeight = MediaQuery.of(context).size.height;
    final maxCanvasW = math.min(440.0, screenWidth - 48.0);
    final maxCanvasH = math.min(340.0, screenHeight * 0.38);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: floors.map<Widget>((floor) {
        final f = floor as Map<String, dynamic>;
        final tables = f['tables'] as List? ?? [];
        final widthM = (f['width_meters'] as num?)?.toDouble() ?? 10;
        final lengthM = (f['length_meters'] as num?)?.toDouble() ?? 10;
        final floorName = f['name'] as String? ?? 'Lantai';

        if (tables.isEmpty) return const SizedBox.shrink();

        // Bounding box: area lantai ∪ semua meja (meter), supaya meja di luar ukuran lantai tetap terlihat.
        var minXm = 0.0;
        var minYm = 0.0;
        var maxXm = widthM;
        var maxYm = lengthM;

        for (final t in tables) {
          final tbl = t as Map<String, dynamic>;
          final x = (tbl['x_meters'] as num?)?.toDouble() ?? 0;
          final y = (tbl['y_meters'] as num?)?.toDouble() ?? 0;
          final w = (tbl['width_meters'] as num?)?.toDouble() ?? 0.8;
          final h = (tbl['length_meters'] as num?)?.toDouble() ?? 0.8;
          minXm = math.min(minXm, x);
          minYm = math.min(minYm, y);
          maxXm = math.max(maxXm, x + w);
          maxYm = math.max(maxYm, y + h);
        }

        final contentWm = math.max(maxXm - minXm, 0.5);
        final contentHm = math.max(maxYm - minYm, 0.5);

        final innerW = maxCanvasW - 2 * _edgePad;
        final innerH = maxCanvasH - 2 * _edgePad;
        // Satu skala untuk X dan Y agar proporsi meja sama seperti di denah asli (tidak dipaksa kotak 35–70px).
        final scale = math.min(innerW / contentWm, innerH / contentHm);

        final canvasW = contentWm * scale + 2 * _edgePad;
        final canvasH = contentHm * scale + 2 * _edgePad;

        return Padding(
          padding: const EdgeInsets.only(bottom: 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      color: AppColors.bluePrimary.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Icon(
                      Icons.map_rounded,
                      color: AppColors.bluePrimary,
                      size: 16,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      floorName,
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: colorScheme.onSurface,
                      ),
                    ),
                  ),
                  Text(
                    '${widthM.toStringAsFixed(0)}m × ${lengthM.toStringAsFixed(0)}m',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: colorScheme.onSurfaceVariant,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  _LegendItem(
                    color: AppColors.orangeWarning,
                    label: 'Ada pesanan',
                    theme: theme,
                  ),
                  const SizedBox(width: 16),
                  _LegendItem(
                    color: colorScheme.surfaceContainerHighest,
                    label: 'Kosong',
                    theme: theme,
                    borderColor: colorScheme.outlineVariant,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Center(
                child: Container(
                  width: canvasW,
                  height: canvasH,
                  decoration: BoxDecoration(
                    color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.25),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: colorScheme.outlineVariant.withValues(alpha: 0.45),
                      width: 1.5,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.05),
                        blurRadius: 6,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(10.5),
                    child: Stack(
                      clipBehavior: Clip.hardEdge,
                      children: [
                        Positioned.fill(
                          child: CustomPaint(
                            painter: _GridPainter(
                              color: colorScheme.outlineVariant.withValues(alpha: 0.12),
                            ),
                          ),
                        ),
                        // Garis area lantai (referensi)
                        Positioned(
                          left: _edgePad + (0 - minXm) * scale,
                          top: _edgePad + (0 - minYm) * scale,
                          width: math.max(widthM * scale, 1),
                          height: math.max(lengthM * scale, 1),
                          child: IgnorePointer(
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                border: Border.all(
                                  color: colorScheme.outlineVariant.withValues(alpha: 0.35),
                                  width: 1,
                                ),
                                borderRadius: BorderRadius.circular(4),
                              ),
                            ),
                          ),
                        ),
                        ...tables.map<Widget>((t) {
                          final tbl = t as Map<String, dynamic>;
                          final tid = tbl['id'] as int?;
                          final name = tbl['name'] as String? ?? '?';
                          final tableX = (tbl['x_meters'] as num?)?.toDouble() ?? 0;
                          final tableY = (tbl['y_meters'] as num?)?.toDouble() ?? 0;
                          final tableW = (tbl['width_meters'] as num?)?.toDouble() ?? 0.8;
                          final tableH = (tbl['length_meters'] as num?)?.toDouble() ?? 0.8;
                          final rotationDeg = (tbl['rotation_deg'] as num?)?.toDouble() ?? 0;
                          final hasOrder = tid != null && pendingTableIds.contains(tid);

                          final left = _edgePad + (tableX - minXm) * scale;
                          final top = _edgePad + (tableY - minYm) * scale;
                          final wPx = math.max(tableW * scale, 22.0).toDouble();
                          final hPx = math.max(tableH * scale, 22.0).toDouble();

                          return Positioned(
                            left: left,
                            top: top,
                            width: wPx,
                            height: hPx,
                            child: _TableMarker(
                              name: name,
                              rotationDeg: rotationDeg,
                              hasOrder: hasOrder,
                              theme: theme,
                              colorScheme: colorScheme,
                            ),
                          );
                        }),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        );
      }).toList(),
    );
  }
}

class _GridPainter extends CustomPainter {
  final Color color;

  _GridPainter({required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = 1;

    const gridSize = 20.0;

    // Draw vertical lines
    for (double x = 0; x < size.width; x += gridSize) {
      canvas.drawLine(
        Offset(x, 0),
        Offset(x, size.height),
        paint,
      );
    }

    // Draw horizontal lines
    for (double y = 0; y < size.height; y += gridSize) {
      canvas.drawLine(
        Offset(0, y),
        Offset(size.width, y),
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _LegendItem extends StatelessWidget {
  final Color color;
  final String label;
  final ThemeData theme;
  final Color? borderColor;

  const _LegendItem({
    required this.color,
    required this.label,
    required this.theme,
    this.borderColor,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 16,
          height: 16,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(4),
            border: borderColor != null
                ? Border.all(color: borderColor!, width: 1)
                : null,
          ),
        ),
        const SizedBox(width: 6),
        Text(
          label,
          style: theme.textTheme.bodySmall?.copyWith(
            fontSize: 11,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}

class _TableMarker extends StatelessWidget {
  final String name;
  final double rotationDeg;
  final bool hasOrder;
  final ThemeData theme;
  final ColorScheme colorScheme;

  const _TableMarker({
    required this.name,
    required this.rotationDeg,
    required this.hasOrder,
    required this.theme,
    required this.colorScheme,
  });

  @override
  Widget build(BuildContext context) {
    final box = LayoutBuilder(
      builder: (context, c) {
        final shortSide = math.min(c.maxWidth, c.maxHeight);
        final nameSize = shortSide < 40 ? 9.0 : 11.0;
        return Container(
          width: c.maxWidth,
          height: c.maxHeight,
          decoration: BoxDecoration(
            color: hasOrder
                ? AppColors.orangeWarning
                : colorScheme.surface.withValues(alpha: 0.95),
            borderRadius: BorderRadius.circular(6),
            border: Border.all(
              color: hasOrder
                  ? Colors.white
                  : colorScheme.outlineVariant.withValues(alpha: 0.65),
              width: hasOrder ? 2 : 1,
            ),
            boxShadow: hasOrder
                ? [
                    BoxShadow(
                      color: AppColors.orangeWarning.withValues(alpha: 0.35),
                      blurRadius: 4,
                      offset: const Offset(0, 1),
                    ),
                  ]
                : null,
          ),
          child: Stack(
            clipBehavior: Clip.hardEdge,
            children: [
              if (hasOrder)
                Positioned(
                  top: 2,
                  right: 2,
                  child: Container(
                    padding: const EdgeInsets.all(2),
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.notifications_active_rounded,
                      color: AppColors.orangeWarning,
                      size: sizeForIcon(shortSide),
                    ),
                  ),
                ),
              Center(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 2),
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          name,
                          maxLines: 2,
                          textAlign: TextAlign.center,
                          style: theme.textTheme.labelMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                            color: hasOrder ? Colors.white : colorScheme.onSurface,
                            fontSize: nameSize,
                            height: 1.1,
                          ),
                        ),
                        if (hasOrder && shortSide > 36) ...[
                          const SizedBox(height: 2),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 3, vertical: 1),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.28),
                              borderRadius: BorderRadius.circular(3),
                            ),
                            child: Text(
                              'PESAN',
                              style: theme.textTheme.labelSmall?.copyWith(
                                fontWeight: FontWeight.bold,
                                color: Colors.white,
                                fontSize: 7,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );

    if (rotationDeg == 0) return box;

    return Transform.rotate(
      angle: rotationDeg * math.pi / 180,
      alignment: Alignment.center,
      child: box,
    );
  }

  double sizeForIcon(double shortSide) {
    if (shortSide < 30) return 8;
    if (shortSide < 44) return 9;
    return 10;
  }
}

class CartItem {
  final Product product;
  final double quantity;
  CartItem({required this.product, required this.quantity});
}
