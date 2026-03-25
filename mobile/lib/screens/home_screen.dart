import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../theme/app_colors.dart';
import '../utils/app_toast.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Map<String, dynamic>? _summary;
  bool _loading = true;
  String? _error;
  int _pendingSyncCount = 0;
  bool _syncing = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadSummary();
      _loadPendingCount();
    });
  }

  Future<void> _loadPendingCount() async {
    final auth = context.read<AuthProvider>();
    final count = await auth.posRepository.getPendingOrdersCount();
    if (mounted) setState(() => _pendingSyncCount = count);
  }

  Future<void> _syncOfflineOrders() async {
    if (_syncing) return;
    setState(() => _syncing = true);
    try {
      final auth = context.read<AuthProvider>();
      final result = await auth.posRepository.syncPendingOrders();
      final newCount = await auth.posRepository.getPendingOrdersCount();
      if (mounted) {
        setState(() {
          _syncing = false;
          _pendingSyncCount = newCount;
        });
        if (result.successCount > 0) {
          AppToast.show(
            context,
            '${result.successCount} pesanan berhasil disinkronkan',
            backgroundColor: Colors.green,
          );
          _loadSummary();
        }
        if (result.failedCount > 0) {
          AppToast.show(
            context,
            '${result.failedCount} pesanan gagal disinkronkan',
            backgroundColor: Colors.orange,
          );
        }
      }
    } catch (_) {
      if (mounted) setState(() => _syncing = false);
    }
  }

  Future<void> _loadSummary() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final auth = context.read<AuthProvider>();
      final summary = await auth.posRepository.getSummaryWithOffline(storeId: auth.selectedStoreId);
      if (mounted) {
        setState(() {
          _summary = summary;
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
    _loadPendingCount();
  }

  /// Setelah kembali dari layar yang bisa mengubah ringkasan (transaksi, antrian, pengeluaran).
  void _openRouteAndRefresh(String route) {
    Navigator.pushNamed(context, route).then((_) {
      if (!mounted) return;
      _loadSummary();
      _loadPendingCount();
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final auth = context.watch<AuthProvider>();
    final user = auth.user!;
    final media = MediaQuery.of(context);
    final isWide = media.size.width >= 600 || media.orientation == Orientation.landscape;

    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async {
            await _loadSummary();
            await _syncOfflineOrders();
          },
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(child: _HomeTopBar(auth: auth)),
              const SliverToBoxAdapter(child: SizedBox(height: 10)),
              if (!_loading &&
                  _summary != null &&
                  _summary!['summary_api_reachable'] == false)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
                    child: Material(
                      color: AppColors.orangeWarning.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(10),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                        child: Row(
                          children: [
                            Icon(Icons.cloud_off_rounded, color: AppColors.orangeWarning, size: 18),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Tidak terhubung ke server. Penjualan & transaksi termasuk yang belum disinkronkan dari perangkat ini.',
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: colorScheme.onSurface,
                                  height: 1.3,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              if (_pendingSyncCount > 0)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
                    child: _SyncPendingCard(
                      count: _pendingSyncCount,
                      syncing: _syncing,
                      onSync: _syncOfflineOrders,
                    ),
                  ),
                ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
                  child: _UserCard(
                    userName: user.name,
                    storeName: auth.selectedStore?.name ?? user.store?.name ?? 'Toko',
                  ),
                ),
              ),

              const SliverToBoxAdapter(child: SizedBox(height: 6)),

              // Content
              if (_loading)
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: Center(
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
                          'Memuat ringkasan...',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colorScheme.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ),
                )
              else if (_error != null)
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: _ErrorState(
                    message: _error!,
                    onRetry: _loadSummary,
                  ),
                )
              else
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(12, 0, 12, 24),
                  sliver: SliverList(
                    delegate: SliverChildListDelegate([
                      Text(
                        'Ringkasan Hari Ini',
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                          color: colorScheme.onSurfaceVariant,
                          fontSize: 13,
                          letterSpacing: 0.2,
                        ),
                      ),
                      const SizedBox(height: 10),
                      isWide
                          ? Row(
                              children: [
                                Expanded(
                                  child: _SummaryCard(
                                    title: 'Menunggu Bayar',
                                    value: '${_summary?['pending_orders_count'] ?? 0}',
                                    icon: Icons.pending_actions_rounded,
                                    color: AppColors.orangeWarning,
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: _SummaryCard(
                                    title: 'Transaksi',
                                    value: '${_summary?['orders_today'] ?? 0}',
                                    icon: Icons.receipt_long_rounded,
                                    color: AppColors.bluePrimary,
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: _SummaryCard(
                                    title: 'Penjualan',
                                    value: _formatCurrency((_summary?['sales_today'] ?? 0).toDouble()),
                                    icon: Icons.trending_up_rounded,
                                    color: AppColors.greenSuccess,
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: _SummaryCard(
                                    title: 'Pengeluaran',
                                    value: _formatCurrency((_summary?['expenses_today'] ?? 0).toDouble()),
                                    icon: Icons.payments_rounded,
                                    color: AppColors.redCancel,
                                  ),
                                ),
                              ],
                            )
                          : Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: _SummaryCard(
                                        title: 'Menunggu Bayar',
                                        value: '${_summary?['pending_orders_count'] ?? 0}',
                                        icon: Icons.pending_actions_rounded,
                                        color: AppColors.orangeWarning,
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: _SummaryCard(
                                        title: 'Transaksi',
                                        value: '${_summary?['orders_today'] ?? 0}',
                                        icon: Icons.receipt_long_rounded,
                                        color: AppColors.bluePrimary,
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 8),
                                _SummaryCard(
                                  title: 'Penjualan Hari Ini',
                                  value: _formatCurrency((_summary?['sales_today'] ?? 0).toDouble()),
                                  icon: Icons.trending_up_rounded,
                                  color: AppColors.greenSuccess,
                                  isWide: true,
                                ),
                                const SizedBox(height: 8),
                                _SummaryCard(
                                  title: 'Pengeluaran Hari Ini',
                                  value: _formatCurrency((_summary?['expenses_today'] ?? 0).toDouble()),
                                  icon: Icons.payments_rounded,
                                  color: AppColors.redCancel,
                                  isWide: true,
                                ),
                              ],
                            ),
                      const SizedBox(height: 20),
                      Text(
                        'Aksi Cepat',
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                          color: colorScheme.onSurfaceVariant,
                          fontSize: 13,
                          letterSpacing: 0.2,
                        ),
                      ),
                      const SizedBox(height: 10),
                      isWide
                          ? Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Expanded(
                                      child: _ActionCard(
                                        icon: Icons.add_shopping_cart_rounded,
                                        label: 'Transaksi Baru',
                                        subtitle: 'Buat pesanan walk-in atau takeaway',
                                        onTap: () => _openRouteAndRefresh('/transaksi'),
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: _ActionCard(
                                        icon: Icons.pending_actions_rounded,
                                        label: 'Pesanan Menunggu',
                                        subtitle: 'Proses pembayaran pesanan dari meja',
                                        onTap: () => _openRouteAndRefresh('/pending-orders'),
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: _ActionCard(
                                        icon: Icons.payments_rounded,
                                        label: 'Pengeluaran Harian',
                                        subtitle: 'Catat pengeluaran toko hari ini',
                                        onTap: () => _openRouteAndRefresh('/expenses'),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 10),
                                _ActionCard(
                                  icon: Icons.history_rounded,
                                  label: 'Riwayat Transaksi',
                                  subtitle: 'Lihat transaksi terbayar (read-only)',
                                  onTap: () => Navigator.pushNamed(context, '/order-history'),
                                ),
                              ],
                            )
                          : _ActionCard(
                              icon: Icons.add_shopping_cart_rounded,
                              label: 'Transaksi Baru',
                              subtitle: 'Buat pesanan walk-in atau takeaway',
                              onTap: () => _openRouteAndRefresh('/transaksi'),
                            ),
                      if (!isWide) const SizedBox(height: 8),
                      if (!isWide)
                        _ActionCard(
                          icon: Icons.pending_actions_rounded,
                          label: 'Pesanan Menunggu',
                          subtitle: 'Proses pembayaran pesanan dari meja',
                          onTap: () => _openRouteAndRefresh('/pending-orders'),
                        ),
                      if (!isWide) const SizedBox(height: 8),
                      if (!isWide)
                        _ActionCard(
                          icon: Icons.payments_rounded,
                          label: 'Pengeluaran Harian',
                          subtitle: 'Catat pengeluaran toko hari ini',
                          onTap: () => _openRouteAndRefresh('/expenses'),
                        ),
                      if (!isWide) const SizedBox(height: 8),
                      if (!isWide)
                        _ActionCard(
                          icon: Icons.history_rounded,
                          label: 'Riwayat Transaksi',
                          subtitle: 'Lihat transaksi terbayar (read-only)',
                          onTap: () => Navigator.pushNamed(context, '/order-history'),
                        ),
                    ]),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  String _formatCurrency(double value) {
    if (value >= 1000000) {
      return 'Rp ${(value / 1000000).toStringAsFixed(1)}jt';
    }
    final s = value.toStringAsFixed(0);
    final buffer = StringBuffer();
    for (var i = 0; i < s.length; i++) {
      if (i > 0 && (s.length - i) % 3 == 0) buffer.write('.');
      buffer.write(s[i]);
    }
    return 'Rp $buffer';
  }
}

class _HomeTopBar extends StatelessWidget {
  final AuthProvider auth;

  const _HomeTopBar({required this.auth});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      decoration: const BoxDecoration(
        color: AppColors.bluePrimary,
        boxShadow: [
          BoxShadow(color: Colors.black26, blurRadius: 6, offset: Offset(0, 1)),
        ],
      ),
      child: SafeArea(
        bottom: false,
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Kasir POS',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                      letterSpacing: -0.3,
                      fontSize: 20,
                    ),
                  ),
                  const SizedBox(height: 1),
                  Text(
                    'Selamat datang kembali',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: Colors.white70,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            if (auth.needsStoreSelection)
              IconButton(
                visualDensity: VisualDensity.compact,
                onPressed: () => Navigator.of(context).pushReplacementNamed('/store-selector'),
                icon: const Icon(Icons.store_rounded, color: Colors.white, size: 22),
                tooltip: 'Ganti Toko',
              ),
            IconButton(
              visualDensity: VisualDensity.compact,
              onPressed: () async {
                final confirm = await showDialog<bool>(
                  context: context,
                  builder: (ctx) => AlertDialog(
                    title: const Text('Keluar', style: TextStyle(fontSize: 17)),
                    content: const Text(
                      'Yakin ingin keluar dari akun?',
                      style: TextStyle(fontSize: 13),
                    ),
                    actions: [
                      TextButton(
                        onPressed: () => Navigator.pop(ctx, false),
                        child: const Text('Batal', style: TextStyle(fontSize: 13)),
                      ),
                      FilledButton(
                        onPressed: () => Navigator.pop(ctx, true),
                        style: FilledButton.styleFrom(
                          visualDensity: VisualDensity.compact,
                        ),
                        child: const Text('Keluar', style: TextStyle(fontSize: 13)),
                      ),
                    ],
                  ),
                );
                if (confirm == true && context.mounted) {
                  auth.logout();
                  Navigator.of(context).pushReplacementNamed('/');
                }
              },
              icon: const Icon(Icons.logout_rounded, color: Colors.white, size: 22),
              tooltip: 'Keluar',
            ),
          ],
        ),
      ),
    );
  }
}

class _SyncPendingCard extends StatelessWidget {
  final int count;
  final bool syncing;
  final VoidCallback onSync;

  const _SyncPendingCard({
    required this.count,
    required this.syncing,
    required this.onSync,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.orangeWarning.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.orangeWarning.withValues(alpha: 0.4)),
      ),
      child: Row(
        children: [
          Icon(Icons.cloud_off_rounded, color: AppColors.orangeWarning, size: 24),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$count pesanan offline',
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                  ),
                ),
                Text(
                  'Tap untuk sinkronkan ke server',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.orangeWarning.withValues(alpha: 0.9),
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          FilledButton.icon(
            onPressed: syncing ? null : onSync,
            icon: syncing
                ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.sync_rounded, size: 17),
            label: Text(
              syncing ? 'Sinkron...' : 'Sinkron',
              style: const TextStyle(fontSize: 12),
            ),
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.orangeWarning,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              visualDensity: VisualDensity.compact,
              minimumSize: Size.zero,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
          ),
        ],
      ),
    );
  }
}

class _UserCard extends StatelessWidget {
  final String userName;
  final String storeName;

  const _UserCard({
    required this.userName,
    required this.storeName,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: colorScheme.outlineVariant.withValues(alpha: 0.5),
          width: 1,
        ),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 22,
            backgroundColor: AppColors.bluePrimary.withValues(alpha: 0.15),
            child: Text(
              userName.isNotEmpty ? userName[0].toUpperCase() : '?',
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
                color: AppColors.bluePrimary,
                fontSize: 18,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  userName,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    fontSize: 15,
                  ),
                ),
                const SizedBox(height: 1),
                Row(
                  children: [
                    Icon(
                      Icons.storefront_rounded,
                      size: 14,
                      color: colorScheme.onSurfaceVariant,
                    ),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        storeName,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: colorScheme.onSurfaceVariant,
                          fontSize: 12,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;
  final Color color;
  final bool isWide;

  const _SummaryCard({
    required this.title,
    required this.value,
    required this.icon,
    required this.color,
    this.isWide = false,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colorScheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: colorScheme.outlineVariant.withValues(alpha: 0.5),
          width: 1,
        ),
        boxShadow: [
          BoxShadow(
            color: colorScheme.shadow.withValues(alpha: 0.06),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: isWide
          ? Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(icon, color: color, size: 22),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        title,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: colorScheme.onSurfaceVariant,
                          fontWeight: FontWeight.w600,
                          fontSize: 11,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        value,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: color,
                          letterSpacing: -0.3,
                          fontSize: 16,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(icon, color: color, size: 20),
                ),
                const SizedBox(height: 8),
                Text(
                  title,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: colorScheme.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                    fontSize: 11,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.3,
                    fontSize: 15,
                  ),
                ),
              ],
            ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final String? subtitle;
  final VoidCallback onTap;

  const _ActionCard({
    required this.icon,
    required this.label,
    this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            color: colorScheme.surface,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: colorScheme.outlineVariant.withValues(alpha: 0.5),
              width: 1,
            ),
            boxShadow: [
              BoxShadow(
                color: colorScheme.shadow.withValues(alpha: 0.06),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppColors.bluePrimary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  icon,
                  size: 22,
                  color: AppColors.bluePrimary,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      label,
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        letterSpacing: -0.2,
                        fontSize: 14,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (subtitle != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        subtitle!,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: colorScheme.onSurfaceVariant,
                          height: 1.25,
                          fontSize: 11,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorState({
    required this.message,
    required this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: colorScheme.errorContainer.withValues(alpha: 0.5),
              shape: BoxShape.circle,
            ),
            child: Icon(
              Icons.error_outline_rounded,
              size: 44,
              color: colorScheme.error,
            ),
          ),
          const SizedBox(height: 14),
          Text(
            'Gagal Memuat',
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w600,
            ),
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
