import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../database/db_helper.dart';
import '../models/order.dart';
import '../providers/auth_provider.dart';
import '../theme/app_colors.dart';
import '../utils/app_toast.dart';

enum _HistoryPeriod { today, week, month, range }

/// Daftar transaksi terbayar (read-only).
/// Online: data server. Offline: transaksi lokal yang belum / gagal disinkron (SQLite).
class OrderHistoryScreen extends StatefulWidget {
  const OrderHistoryScreen({super.key});

  @override
  State<OrderHistoryScreen> createState() => _OrderHistoryScreenState();
}

class _OrderHistoryScreenState extends State<OrderHistoryScreen> {
  final List<Order> _orders = [];
  /// Baris dari [pending_orders] saat mode offline / fallback.
  final List<Map<String, dynamic>> _localRows = [];
  bool _offlineMode = false;
  int _page = 1;
  int _lastPage = 1;
  int _metaTotal = 0;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  _HistoryPeriod _period = _HistoryPeriod.week;
  DateTime? _rangeStart;
  DateTime? _rangeEnd;

  static final _timeFmt = DateFormat('dd MMM yyyy, HH:mm');
  static final _dateChipFmt = DateFormat('dd MMM yyyy');

  String get _filterDescription {
    if (_period == _HistoryPeriod.range &&
        _rangeStart != null &&
        _rangeEnd != null) {
      return '${_dateChipFmt.format(_rangeStart!)} – ${_dateChipFmt.format(_rangeEnd!)}';
    }
    switch (_period) {
      case _HistoryPeriod.today:
        return 'Hari ini';
      case _HistoryPeriod.week:
        return '7 hari terakhir';
      case _HistoryPeriod.month:
        return '30 hari terakhir';
      case _HistoryPeriod.range:
        return 'Rentang tanggal';
    }
  }

  String _formatCurrency(double value) {
    return NumberFormat.currency(
      locale: 'id_ID',
      symbol: 'Rp ',
      decimalDigits: 0,
    ).format(value);
  }

  double get _sumLoaded {
    if (_offlineMode) {
      return _localRows.fold<double>(
        0,
        (sum, o) => sum + ((o['total'] as num?)?.toDouble() ?? 0),
      );
    }
    return _orders.fold<double>(0, (sum, o) => sum + o.finalAmount);
  }

  Future<List<Map<String, dynamic>>> _fetchLocalRows(int storeId) async {
    if (_period == _HistoryPeriod.range &&
        _rangeStart != null &&
        _rangeEnd != null) {
      final a = _rangeStart!;
      final b = _rangeEnd!;
      final from = a.isBefore(b) ? a : b;
      final to = a.isBefore(b) ? b : a;
      return DbHelper.getOfflineLocalOrdersForHistory(
        storeId,
        days: 7,
        rangeStart: from,
        rangeEnd: to,
      );
    }
    final days = switch (_period) {
      _HistoryPeriod.today => 1,
      _HistoryPeriod.week => 7,
      _HistoryPeriod.month => 30,
      _HistoryPeriod.range => 7,
    };
    return DbHelper.getOfflineLocalOrdersForHistory(storeId, days: days);
  }

  @override
  void initState() {
    super.initState();
    _load(refresh: true);
  }

  Future<void> _load({bool refresh = false}) async {
    if (refresh) {
      setState(() {
        _loading = true;
        _error = null;
        _page = 1;
      });
    } else {
      if (_loadingMore || _page >= _lastPage) return;
      setState(() => _loadingMore = true);
    }

    try {
      final auth = context.read<AuthProvider>();
      final targetPage = refresh ? 1 : _page + 1;
      final Map<String, dynamic> data;
      if (_period == _HistoryPeriod.range &&
          _rangeStart != null &&
          _rangeEnd != null) {
        final a = _rangeStart!;
        final b = _rangeEnd!;
        final from = a.isBefore(b) ? a : b;
        final to = a.isBefore(b) ? b : a;
        data = await auth.api.getOrderHistory(
          storeId: auth.selectedStoreId,
          dateFrom: DateFormat('yyyy-MM-dd').format(from),
          dateTo: DateFormat('yyyy-MM-dd').format(to),
          page: targetPage,
          perPage: 20,
        );
      } else {
        final days = switch (_period) {
          _HistoryPeriod.today => 1,
          _HistoryPeriod.week => 7,
          _HistoryPeriod.month => 30,
          _HistoryPeriod.range => 7,
        };
        data = await auth.api.getOrderHistory(
          storeId: auth.selectedStoreId,
          days: days,
          page: targetPage,
          perPage: 20,
        );
      }
      if (!mounted) return;
      final list = (data['orders'] as List)
          .map((e) => Order.fromJson(e as Map<String, dynamic>))
          .toList();
      final meta = data['meta'] as Map<String, dynamic>? ?? {};
      final last = (meta['last_page'] as num?)?.toInt() ?? 1;
      final total = (meta['total'] as num?)?.toInt() ?? list.length;
      setState(() {
        if (refresh) {
          _orders
            ..clear()
            ..addAll(list);
        } else {
          _orders.addAll(list);
        }
        _offlineMode = false;
        _localRows.clear();
        _page = (meta['current_page'] as num?)?.toInt() ?? targetPage;
        _lastPage = last;
        _metaTotal = total;
        _loading = false;
        _loadingMore = false;
        _error = null;
      });
    } catch (e) {
      if (!mounted) return;
      final auth = context.read<AuthProvider>();
      final storeId = auth.selectedStoreId;

      if (refresh && storeId != null) {
        try {
          final local = await _fetchLocalRows(storeId);
          if (!mounted) return;
          setState(() {
            _offlineMode = true;
            _error = null;
            _loading = false;
            _loadingMore = false;
            _orders.clear();
            _localRows
              ..clear()
              ..addAll(local);
            _metaTotal = local.length;
            _lastPage = 1;
            _page = 1;
          });
          return;
        } catch (_) {
          // jatuh ke error umum
        }
      }

      if (!refresh) {
        setState(() => _loadingMore = false);
        if (mounted) {
          AppToast.show(
            context,
            'Gagal memuat halaman berikutnya. ${e.toString().replaceAll('ApiException: ', '')}',
            backgroundColor: AppColors.orangeWarning,
          );
        }
        return;
      }

      if (mounted) {
        setState(() {
          _error = e.toString().replaceAll('ApiException: ', '');
          _loading = false;
          _loadingMore = false;
          _offlineMode = false;
        });
      }
    }
  }

  Future<void> _pickDateRange() async {
    final now = DateTime.now();
    final initial = _rangeStart != null && _rangeEnd != null
        ? DateTimeRange(start: _rangeStart!, end: _rangeEnd!)
        : DateTimeRange(
            start: now.subtract(const Duration(days: 7)),
            end: now,
          );
    final range = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2020),
      lastDate: now,
      initialDateRange: initial,
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: ColorScheme.fromSeed(
              seedColor: AppColors.bluePrimary,
              brightness: Theme.of(context).brightness,
            ),
          ),
          child: child!,
        );
      },
    );
    if (range != null && mounted) {
      setState(() {
        _period = _HistoryPeriod.range;
        _rangeStart = range.start;
        _rangeEnd = range.end;
      });
      _load(refresh: true);
    }
  }

  void _setPeriod(_HistoryPeriod p) {
    if (p == _HistoryPeriod.range) {
      _pickDateRange();
      return;
    }
    setState(() {
      _period = p;
      _rangeStart = null;
      _rangeEnd = null;
    });
    _load(refresh: true);
  }

  Future<void> _showDetail(Order summary) async {
    final auth = context.read<AuthProvider>();
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.88,
        ),
        decoration: BoxDecoration(
          color: colorScheme.surface,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.2),
              blurRadius: 20,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: FutureBuilder<Order>(
          future: auth.api.getOrder(summary.id),
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return Padding(
                padding: const EdgeInsets.all(48),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      padding: const EdgeInsets.only(top: 12, bottom: 8),
                      child: Container(
                        width: 40,
                        height: 4,
                        decoration: BoxDecoration(
                          color: colorScheme.outlineVariant,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                    const SizedBox(
                      width: 36,
                      height: 36,
                      child: CircularProgressIndicator(strokeWidth: 2.5),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Memuat detail...',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              );
            }
            if (snap.hasError) {
              return Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.error_outline_rounded, size: 48, color: colorScheme.error),
                    const SizedBox(height: 12),
                    Text(
                      'Gagal memuat detail',
                      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      '${snap.error}',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodySmall?.copyWith(color: colorScheme.onSurfaceVariant),
                    ),
                  ],
                ),
              );
            }
            final o = snap.data!;
            return Column(
              children: [
                Padding(
                  padding: const EdgeInsets.only(top: 12, bottom: 8),
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: colorScheme.outlineVariant,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 8, 12, 16),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: AppColors.greenSuccess.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Icon(
                          Icons.receipt_long_rounded,
                          color: AppColors.greenSuccess,
                          size: 28,
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              o.orderCode,
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              _typeLabel(o.type),
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: colorScheme.onSurfaceVariant,
                              ),
                            ),
                            if (o.paidAt != null)
                              Text(
                                'Dibayar ${_timeFmt.format(DateTime.parse(o.paidAt!))}',
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: AppColors.greenSuccess,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                          ],
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close_rounded),
                        onPressed: () => Navigator.pop(ctx),
                      ),
                    ],
                  ),
                ),
                const Divider(height: 1),
                Expanded(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: colorScheme.outlineVariant.withValues(alpha: 0.3),
                            ),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Item',
                                style: theme.textTheme.titleSmall?.copyWith(
                                  fontWeight: FontWeight.w600,
                                  color: colorScheme.onSurfaceVariant,
                                ),
                              ),
                              const SizedBox(height: 12),
                              ...o.items.map((i) {
                                final qtyLabel = i.quantity % 1 == 0
                                    ? i.quantity.toInt().toString()
                                    : i.quantity.toString();
                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 12),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 8,
                                          vertical: 4,
                                        ),
                                        decoration: BoxDecoration(
                                          color: AppColors.bluePrimary.withValues(alpha: 0.12),
                                          borderRadius: BorderRadius.circular(6),
                                        ),
                                        child: Text(
                                          '${qtyLabel}x',
                                          style: theme.textTheme.labelMedium?.copyWith(
                                            color: AppColors.bluePrimary,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                      const SizedBox(width: 10),
                                      Expanded(
                                        child: Text(
                                          i.productName,
                                          style: theme.textTheme.bodyMedium?.copyWith(
                                            fontWeight: FontWeight.w500,
                                          ),
                                        ),
                                      ),
                                      Text(
                                        _formatCurrency(i.subtotal),
                                        style: theme.textTheme.bodyMedium?.copyWith(
                                          fontWeight: FontWeight.w600,
                                          color: AppColors.bluePrimary,
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              }),
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),
                        Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.25),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: colorScheme.outlineVariant.withValues(alpha: 0.25),
                            ),
                          ),
                          child: Column(
                            children: [
                              _DetailRow(
                                label: 'Subtotal',
                                value: _formatCurrency(o.subtotal),
                              ),
                              if (o.discountAmount > 0)
                                _DetailRow(
                                  label: 'Diskon',
                                  value: '- ${_formatCurrency(o.discountAmount)}',
                                  valueColor: AppColors.redCancel,
                                ),
                              if (o.taxAmount > 0)
                                _DetailRow(
                                  label: 'Pajak',
                                  value: _formatCurrency(o.taxAmount),
                                ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 12),
                        Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: AppColors.greenSuccess.withValues(alpha: 0.08),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: AppColors.greenSuccess.withValues(alpha: 0.2),
                            ),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                'Total',
                                style: theme.textTheme.titleLarge?.copyWith(
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              Text(
                                _formatCurrency(o.finalAmount),
                                style: theme.textTheme.titleLarge?.copyWith(
                                  fontWeight: FontWeight.bold,
                                  color: AppColors.greenSuccess,
                                ),
                              ),
                            ],
                          ),
                        ),
                        if (o.paymentMethod != null) ...[
                          const SizedBox(height: 14),
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: AppColors.bluePrimary.withValues(alpha: 0.06),
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(
                                color: AppColors.bluePrimary.withValues(alpha: 0.15),
                              ),
                            ),
                            child: Row(
                              children: [
                                Icon(
                                  Icons.payment_rounded,
                                  size: 20,
                                  color: AppColors.bluePrimary,
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        'Metode pembayaran',
                                        style: theme.textTheme.bodySmall?.copyWith(
                                          color: colorScheme.onSurfaceVariant,
                                        ),
                                      ),
                                      Text(
                                        o.paymentMethod!,
                                        style: theme.textTheme.bodyMedium?.copyWith(
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                        if (o.cashReceived != null) ...[
                          const SizedBox(height: 10),
                          _DetailRow(
                            label: 'Dibayar tunai',
                            value: _formatCurrency(o.cashReceived!),
                          ),
                          if (o.changeAmount != null && o.changeAmount! > 0)
                            _DetailRow(
                              label: 'Kembalian',
                              value: _formatCurrency(o.changeAmount!),
                              valueColor: AppColors.bluePrimary,
                            ),
                        ],
                        const SizedBox(height: 8),
                        Text(
                          'Riwayat hanya baca — tidak dapat diubah.',
                          textAlign: TextAlign.center,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colorScheme.onSurfaceVariant.withValues(alpha: 0.8),
                            fontStyle: FontStyle.italic,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  Future<void> _showLocalDetail(Map<String, dynamic> row) async {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final storeId = context.read<AuthProvider>().selectedStoreId;
    if (storeId == null) return;

    final products = await DbHelper.getProducts(storeId);
    final nameById = {for (final p in products) p.id: p.name};

    final itemsJson = row['items_json'] as String? ?? '[]';
    List<dynamic> rawItems;
    try {
      rawItems = jsonDecode(itemsJson) as List<dynamic>;
    } catch (_) {
      rawItems = [];
    }

    final code = row['local_order_code'] as String? ?? '-';
    final orderType = row['order_type'] as String? ?? '';
    final payment = row['payment_method'] as String? ?? '-';
    final total = (row['total'] as num?)?.toDouble() ?? 0;
    final cash = (row['cash_received'] as num?)?.toDouble();
    final status = row['sync_status'] as String? ?? 'pending';
    final createdMs = row['created_at'] as int? ?? 0;
    final created = DateTime.fromMillisecondsSinceEpoch(createdMs);

    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.88,
        ),
        decoration: BoxDecoration(
          color: colorScheme.surface,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.2),
              blurRadius: 20,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.only(top: 12, bottom: 8),
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: colorScheme.outlineVariant,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 12, 16),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: AppColors.orangeWarning.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      Icons.cloud_off_rounded,
                      color: AppColors.orangeWarning,
                      size: 28,
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          code,
                          style: theme.textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        Text(
                          _typeLabel(orderType),
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: colorScheme.onSurfaceVariant,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Icon(
                              Icons.schedule_rounded,
                              size: 14,
                              color: colorScheme.onSurfaceVariant,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              _timeFmt.format(created),
                              style: theme.textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close_rounded),
                    onPressed: () => Navigator.pop(ctx),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                      decoration: BoxDecoration(
                        color: (status == 'failed'
                                ? AppColors.redCancel
                                : AppColors.orangeWarning)
                            .withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(
                          color: (status == 'failed'
                                  ? AppColors.redCancel
                                  : AppColors.orangeWarning)
                              .withValues(alpha: 0.25),
                        ),
                      ),
                      child: Text(
                        status == 'failed'
                            ? 'Gagal sinkron — masih bisa dicoba lagi dari antrian'
                            : 'Belum disinkron ke server',
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: status == 'failed'
                              ? AppColors.redCancel
                              : AppColors.orangeWarning,
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: colorScheme.outlineVariant.withValues(alpha: 0.3),
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Item',
                            style: theme.textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 12),
                          ...rawItems.map((e) {
                            final m = e as Map<String, dynamic>;
                            final pid = m['product_id'] as int;
                            final qty = (m['quantity'] as num).toDouble();
                            final name = nameById[pid] ?? 'Produk #$pid';
                            final qtyLabel =
                                qty % 1 == 0 ? qty.toInt().toString() : qty.toString();
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 8,
                                      vertical: 4,
                                    ),
                                    decoration: BoxDecoration(
                                      color: AppColors.bluePrimary.withValues(alpha: 0.12),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      '${qtyLabel}x',
                                      style: theme.textTheme.labelMedium?.copyWith(
                                        color: AppColors.bluePrimary,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Text(
                                      name,
                                      style: theme.textTheme.bodyMedium?.copyWith(
                                        fontWeight: FontWeight.w500,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            );
                          }),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: AppColors.greenSuccess.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: AppColors.greenSuccess.withValues(alpha: 0.2),
                        ),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Total',
                            style: theme.textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Text(
                            _formatCurrency(total),
                            style: theme.textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.bold,
                              color: AppColors.greenSuccess,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.bluePrimary.withValues(alpha: 0.06),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(
                          color: AppColors.bluePrimary.withValues(alpha: 0.15),
                        ),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            Icons.payment_rounded,
                            size: 20,
                            color: AppColors.bluePrimary,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Metode pembayaran',
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: colorScheme.onSurfaceVariant,
                                  ),
                                ),
                                Text(
                                  payment,
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (cash != null) ...[
                      const SizedBox(height: 10),
                      _DetailRow(
                        label: 'Dibayar tunai',
                        value: _formatCurrency(cash),
                      ),
                    ],
                    const SizedBox(height: 8),
                    Text(
                      'Data disimpan di perangkat — akan masuk server setelah sinkron.',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: colorScheme.onSurfaceVariant.withValues(alpha: 0.85),
                        fontStyle: FontStyle.italic,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final width = MediaQuery.of(context).size.width;
    final isWide = width >= 600;

    return Scaffold(
      body: Column(
        children: [
          Container(
            width: double.infinity,
            decoration: const BoxDecoration(
              color: AppColors.bluePrimary,
              boxShadow: [
                BoxShadow(
                  color: Colors.black26,
                  blurRadius: 6,
                  offset: Offset(0, 2),
                ),
              ],
            ),
            child: SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                child: Row(
                  children: [
                    IconButton(
                      style: IconButton.styleFrom(
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.all(8),
                        minimumSize: const Size(40, 40),
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      ),
                      icon: const Icon(Icons.arrow_back_rounded, size: 22),
                      onPressed: () => Navigator.pop(context),
                    ),
                    Expanded(
                      child: Text(
                        'Riwayat Transaksi',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    IconButton(
                      style: IconButton.styleFrom(
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.all(8),
                        minimumSize: const Size(40, 40),
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      ),
                      icon: const Icon(Icons.refresh_rounded, size: 22),
                      tooltip: 'Muat ulang',
                      onPressed: _loading ? null : () => _load(refresh: true),
                    ),
                  ],
                ),
              ),
            ),
          ),
          Expanded(
            child: Align(
              alignment: Alignment.topCenter,
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 680),
                child: _buildBody(theme, isWide),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBody(ThemeData theme, bool isWide) {
    final colorScheme = theme.colorScheme;

    if (_loading) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            SizedBox(
              width: 32,
              height: 32,
              child: CircularProgressIndicator(
                strokeWidth: 2.5,
                color: AppColors.bluePrimary,
              ),
            ),
            SizedBox(height: 16),
            Text('Memuat riwayat...'),
          ],
        ),
      );
    }

    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                Icons.cloud_off_rounded,
                size: 64,
                color: colorScheme.onSurfaceVariant.withValues(alpha: 0.5),
              ),
              const SizedBox(height: 24),
              Text(
                'Gagal Memuat',
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                _error!,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 24),
              FilledButton.icon(
                onPressed: () => _load(refresh: true),
                icon: const Icon(Icons.refresh_rounded, size: 20),
                label: const Text('Coba Lagi'),
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.bluePrimary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                ),
              ),
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => _load(refresh: true),
      color: AppColors.bluePrimary,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _PeriodFilterChips(
                    period: _period,
                    onPeriodChanged: _setPeriod,
                  ),
                  if (_offlineMode) ...[
                    const SizedBox(height: 12),
                    Material(
                      color: AppColors.orangeWarning.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(10),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Icon(
                              Icons.cloud_off_rounded,
                              color: AppColors.orangeWarning,
                              size: 22,
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                'Tidak ada koneksi ke server. Menampilkan transaksi lokal (belum / gagal disinkron) untuk periode ini. Riwayat lengkap dari server tersedia saat online.',
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: colorScheme.onSurface,
                                  height: 1.35,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          AppColors.greenSuccess.withValues(alpha: 0.08),
                          AppColors.greenSuccess.withValues(alpha: 0.14),
                        ],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: AppColors.greenSuccess.withValues(alpha: 0.22),
                      ),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: AppColors.greenSuccess.withValues(alpha: 0.15),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: const Icon(
                                Icons.summarize_rounded,
                                color: AppColors.greenSuccess,
                                size: 20,
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _offlineMode
                                    ? 'Filter: $_filterDescription (lokal)'
                                    : 'Filter: $_filterDescription',
                                style: theme.textTheme.bodyMedium?.copyWith(
                                  color: colorScheme.onSurface,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Divider(
                          color: AppColors.greenSuccess.withValues(alpha: 0.2),
                          height: 1,
                        ),
                        const SizedBox(height: 12),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Total transaksi',
                              style: theme.textTheme.titleSmall?.copyWith(
                                color: colorScheme.onSurfaceVariant,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            Text(
                              '$_metaTotal',
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.bold,
                                color: AppColors.greenSuccess,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              _offlineMode ? 'Total nilai (lokal)' : 'Nilai terjumlah (dimuat)',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: colorScheme.onSurfaceVariant,
                              ),
                            ),
                            Text(
                              _formatCurrency(_sumLoaded),
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.bold,
                                color: AppColors.greenSuccess,
                              ),
                            ),
                          ],
                        ),
                        if (!_offlineMode && _page < _lastPage)
                          Padding(
                            padding: const EdgeInsets.only(top: 10),
                            child: Text(
                              'Belum semua dimuat — gunakan "Muat lebih banyak" di bawah.',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: AppColors.orangeWarning,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    decoration: BoxDecoration(
                      color: AppColors.bluePrimary.withValues(alpha: 0.06),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: AppColors.bluePrimary.withValues(alpha: 0.12),
                      ),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          Icons.info_outline_rounded,
                          size: 20,
                          color: AppColors.bluePrimary,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            _offlineMode
                                ? 'Transaksi ini tersimpan di perangkat sampai berhasil disinkron ke server.'
                                : 'Hanya transaksi yang sudah terbayar di server. Saat offline, daftar di atas diganti oleh transaksi lokal yang belum disinkron.',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: colorScheme.onSurfaceVariant,
                              height: 1.35,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (_orders.isEmpty && _localRows.isEmpty)
            SliverFillRemaining(
              hasScrollBody: false,
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(24),
                      decoration: BoxDecoration(
                        color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.35),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        _offlineMode ? Icons.cloud_off_rounded : Icons.receipt_long_rounded,
                        size: 48,
                        color: colorScheme.onSurfaceVariant.withValues(alpha: 0.45),
                      ),
                    ),
                    const SizedBox(height: 20),
                    Text(
                      _offlineMode
                          ? 'Tidak Ada Transaksi Lokal'
                          : 'Belum Ada Transaksi',
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: colorScheme.onSurfaceVariant,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _offlineMode
                          ? 'Tidak ada order offline untuk periode ini, atau sudah tersinkron. Riwayat server akan tampil saat online.'
                          : 'Sesuaikan filter tanggal atau coba lagi nanti',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: colorScheme.onSurfaceVariant.withValues(alpha: 0.75),
                      ),
                    ),
                  ],
                ),
              ),
            )
          else if (_offlineMode)
            SliverPadding(
              padding: EdgeInsets.fromLTRB(16, 8, 16, isWide ? 24 : 24),
              sliver: SliverList(
                delegate: SliverChildBuilderDelegate(
                  (context, i) {
                    final row = _localRows[i];
                    final createdMs = row['created_at'] as int? ?? 0;
                    final paid = _timeFmt.format(
                      DateTime.fromMillisecondsSinceEpoch(createdMs),
                    );
                    return _LocalOfflineOrderCard(
                      row: row,
                      paidLabel: paid,
                      formatCurrency: _formatCurrency,
                      onTap: () => _showLocalDetail(row),
                    );
                  },
                  childCount: _localRows.length,
                ),
              ),
            )
          else
            SliverPadding(
              padding: EdgeInsets.fromLTRB(16, 8, 16, isWide ? 24 : 24),
              sliver: SliverList(
                delegate: SliverChildBuilderDelegate(
                  (context, i) {
                    if (i < _orders.length) {
                      final o = _orders[i];
                      final paid = o.paidAt != null
                          ? _timeFmt.format(DateTime.parse(o.paidAt!))
                          : '-';
                      return _HistoryOrderCard(
                        order: o,
                        paidLabel: paid,
                        formatCurrency: _formatCurrency,
                        onTap: () => _showDetail(o),
                      );
                    }
                    if (_page < _lastPage) {
                      return Padding(
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        child: Center(
                          child: _loadingMore
                              ? const SizedBox(
                                  width: 28,
                                  height: 28,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2.5,
                                    color: AppColors.bluePrimary,
                                  ),
                                )
                              : OutlinedButton.icon(
                                  onPressed: () => _load(refresh: false),
                                  icon: const Icon(Icons.expand_more_rounded, size: 20),
                                  label: const Text('Muat lebih banyak'),
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: AppColors.bluePrimary,
                                    side: const BorderSide(color: AppColors.bluePrimary),
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 20,
                                      vertical: 12,
                                    ),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                  ),
                                ),
                        ),
                      );
                    }
                    return const SizedBox.shrink();
                  },
                  childCount: _orders.length + (_page < _lastPage ? 1 : 0),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _PeriodFilterChips extends StatelessWidget {
  final _HistoryPeriod period;
  final void Function(_HistoryPeriod) onPeriodChanged;

  const _PeriodFilterChips({
    required this.period,
    required this.onPeriodChanged,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    Widget chip(String label, _HistoryPeriod p) {
      final selected = period == p;
      return FilterChip(
        label: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
            color: selected ? AppColors.bluePrimary : colorScheme.onSurface,
          ),
        ),
        selected: selected,
        showCheckmark: false,
        onSelected: (_) => onPeriodChanged(p),
        selectedColor: AppColors.bluePrimary.withValues(alpha: 0.14),
        backgroundColor: colorScheme.surfaceContainerHighest.withValues(alpha: 0.45),
        side: BorderSide(
          color: selected
              ? AppColors.bluePrimary.withValues(alpha: 0.55)
              : colorScheme.outlineVariant.withValues(alpha: 0.5),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(
              Icons.filter_list_rounded,
              size: 18,
              color: AppColors.bluePrimary,
            ),
            const SizedBox(width: 6),
            Text(
              'Periode',
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w600,
                color: colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            chip('Hari ini', _HistoryPeriod.today),
            chip('7 hari', _HistoryPeriod.week),
            chip('30 hari', _HistoryPeriod.month),
            chip('Rentang', _HistoryPeriod.range),
          ],
        ),
        const SizedBox(height: 6),
        Text(
          'Tap "Rentang" untuk pilih tanggal mulai & akhir.',
          style: theme.textTheme.bodySmall?.copyWith(
            color: colorScheme.onSurfaceVariant.withValues(alpha: 0.85),
            fontSize: 12,
          ),
        ),
      ],
    );
  }
}

class _LocalOfflineOrderCard extends StatelessWidget {
  final Map<String, dynamic> row;
  final String paidLabel;
  final String Function(double) formatCurrency;
  final VoidCallback onTap;

  const _LocalOfflineOrderCard({
    required this.row,
    required this.paidLabel,
    required this.formatCurrency,
    required this.onTap,
  });

  Color _typeColor(String t) {
    switch (t) {
      case 'dine_in':
        return AppColors.orangeWarning;
      case 'takeaway':
        return AppColors.bluePrimary;
      case 'walk_in':
        return AppColors.greenSuccess;
      default:
        return AppColors.bluePrimary;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final orderType = row['order_type'] as String? ?? '';
    final code = row['local_order_code'] as String? ?? '-';
    final total = (row['total'] as num?)?.toDouble() ?? 0;
    final status = row['sync_status'] as String? ?? 'pending';
    final tc = _typeColor(orderType);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: Ink(
            decoration: BoxDecoration(
              color: colorScheme.surface,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: (status == 'failed' ? AppColors.redCancel : tc)
                    .withValues(alpha: 0.35),
              ),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.04),
                  blurRadius: 4,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: AppColors.orangeWarning.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(
                      Icons.cloud_upload_rounded,
                      color: AppColors.orangeWarning,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          code,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Wrap(
                          spacing: 6,
                          runSpacing: 4,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 3,
                              ),
                              decoration: BoxDecoration(
                                color: tc.withValues(alpha: 0.1),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                _typeLabel(orderType),
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: tc,
                                  fontWeight: FontWeight.w600,
                                  fontSize: 11,
                                ),
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 3,
                              ),
                              decoration: BoxDecoration(
                                color: (status == 'failed'
                                        ? AppColors.redCancel
                                        : AppColors.orangeWarning)
                                    .withValues(alpha: 0.12),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                status == 'failed' ? 'Gagal sync' : 'Belum sync',
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: status == 'failed'
                                      ? AppColors.redCancel
                                      : AppColors.orangeWarning,
                                  fontWeight: FontWeight.w600,
                                  fontSize: 11,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Icon(
                              Icons.schedule_rounded,
                              size: 13,
                              color: colorScheme.onSurfaceVariant.withValues(alpha: 0.8),
                            ),
                            const SizedBox(width: 4),
                            Expanded(
                              child: Text(
                                paidLabel,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: colorScheme.onSurfaceVariant,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        formatCurrency(total),
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                          color: AppColors.greenSuccess,
                          fontSize: 15,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Icon(
                        Icons.chevron_right_rounded,
                        size: 20,
                        color: colorScheme.onSurfaceVariant.withValues(alpha: 0.5),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _HistoryOrderCard extends StatelessWidget {
  final Order order;
  final String paidLabel;
  final String Function(double) formatCurrency;
  final VoidCallback onTap;

  const _HistoryOrderCard({
    required this.order,
    required this.paidLabel,
    required this.formatCurrency,
    required this.onTap,
  });

  IconData _typeIcon(String t) {
    switch (t) {
      case 'dine_in':
        return Icons.restaurant_rounded;
      case 'takeaway':
        return Icons.takeout_dining_rounded;
      case 'walk_in':
        return Icons.point_of_sale_rounded;
      default:
        return Icons.receipt_rounded;
    }
  }

  Color _typeColor(String t) {
    switch (t) {
      case 'dine_in':
        return AppColors.orangeWarning;
      case 'takeaway':
        return AppColors.bluePrimary;
      case 'walk_in':
        return AppColors.greenSuccess;
      default:
        return AppColors.bluePrimary;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final tc = _typeColor(order.type);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: Ink(
            decoration: BoxDecoration(
              color: colorScheme.surface,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: colorScheme.outlineVariant.withValues(alpha: 0.35),
              ),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.04),
                  blurRadius: 4,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: tc.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(_typeIcon(order.type), color: tc, size: 24),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          order.orderCode,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: tc.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            _typeLabel(order.type),
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: tc,
                              fontWeight: FontWeight.w600,
                              fontSize: 11,
                            ),
                          ),
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Icon(
                              Icons.schedule_rounded,
                              size: 13,
                              color: colorScheme.onSurfaceVariant.withValues(alpha: 0.8),
                            ),
                            const SizedBox(width: 4),
                            Expanded(
                              child: Text(
                                paidLabel,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: colorScheme.onSurfaceVariant,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        formatCurrency(order.finalAmount),
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                          color: AppColors.greenSuccess,
                          fontSize: 15,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Icon(
                        Icons.chevron_right_rounded,
                        size: 20,
                        color: colorScheme.onSurfaceVariant.withValues(alpha: 0.5),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;
  final Color? valueColor;

  const _DetailRow({
    required this.label,
    required this.value,
    this.valueColor,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          Text(
            value,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w600,
              color: valueColor,
            ),
          ),
        ],
      ),
    );
  }
}

String _typeLabel(String t) {
  switch (t) {
    case 'dine_in':
      return 'Makan di tempat';
    case 'takeaway':
      return 'Bawa pulang';
    case 'walk_in':
      return 'Walk-in / kasir';
    default:
      return t;
  }
}
