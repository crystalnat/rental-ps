import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../models/daily_expense.dart';
import '../providers/auth_provider.dart';
import '../theme/app_colors.dart';
import '../utils/app_toast.dart';

class ExpensesScreen extends StatefulWidget {
  const ExpensesScreen({super.key});

  @override
  State<ExpensesScreen> createState() => _ExpensesScreenState();
}

class _ExpensesScreenState extends State<ExpensesScreen> {
  List<DailyExpense> _expenses = [];
  double _totalAmount = 0;
  List<Map<String, String>> _categoryOptions = [];
  bool _loading = true;
  String? _error;
  DateTime _dateFrom = DateTime.now();
  DateTime _dateTo = DateTime.now();

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
      final data = await auth.api.getExpenses(
        storeId: auth.selectedStoreId,
        dateFrom: _formatDate(_dateFrom),
        dateTo: _formatDate(_dateTo),
      );
      if (mounted) {
        final list = data['expenses'] as List? ?? [];
        final opts = data['category_options'] as List? ?? [];
        setState(() {
          _expenses = list
              .map((e) => DailyExpense.fromJson(e as Map<String, dynamic>))
              .toList();
          _totalAmount = (data['total_amount'] as num?)?.toDouble() ?? 0;
          _categoryOptions = opts
              .map((e) => {
                    'value': (e as Map)['value'] as String,
                    'label': (e)['label'] as String,
                  })
              .toList();
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

  String _formatDate(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

  DateTime _todayDateOnly() {
    final n = DateTime.now();
    return DateTime(n.year, n.month, n.day);
  }

  Future<void> _pickDateRange() async {
    final range = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      initialDateRange: DateTimeRange(start: _dateFrom, end: _dateTo),
    );
    if (range != null && mounted) {
      setState(() {
        _dateFrom = range.start;
        _dateTo = range.end;
      });
      _load();
    }
  }

  Future<void> _showAddExpense() async {
    final auth = context.read<AuthProvider>();
    final canPickExpenseDate = auth.canBackdateDailyExpense;
    if (_categoryOptions.isEmpty) {
      AppToast.show(context, 'Memuat kategori...');
      return;
    }
    String? category = _categoryOptions.first['value'];
    final descController = TextEditingController();
    final amountController = TextEditingController();
    DateTime expenseDate = _todayDateOnly();

    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModal) {
          final theme = Theme.of(ctx);
          final colorScheme = theme.colorScheme;

          return Container(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.of(ctx).size.height * 0.78,
            ),
            decoration: BoxDecoration(
              color: colorScheme.surface,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.2),
                  blurRadius: 20,
                  offset: const Offset(0, -4),
                ),
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
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
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 4, 8, 8),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: AppColors.redCancel.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(
                          Icons.add_rounded,
                          color: AppColors.redCancel,
                          size: 22,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'Tambah Pengeluaran',
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                            fontSize: 17,
                          ),
                        ),
                      ),
                      IconButton(
                        visualDensity: VisualDensity.compact,
                        icon: const Icon(Icons.close_rounded, size: 22),
                        onPressed: () => Navigator.pop(ctx, false),
                      ),
                    ],
                  ),
                ),
                const Divider(height: 1),
                Flexible(
                  child: SingleChildScrollView(
                    padding: EdgeInsets.fromLTRB(
                      16,
                      12,
                      16,
                      16 + MediaQuery.of(ctx).viewInsets.bottom,
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        DropdownButtonFormField<String>(
                          value: category,
                          decoration: InputDecoration(
                            labelText: 'Kategori',
                            labelStyle: const TextStyle(fontSize: 12),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                            filled: true,
                            isDense: true,
                            fillColor: colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                          ),
                          items: _categoryOptions
                              .map((o) => DropdownMenuItem(
                                    value: o['value'],
                                    child: Text(o['label']!, style: const TextStyle(fontSize: 13)),
                                  ))
                              .toList(),
                          onChanged: (v) {
                            category = v;
                            setModal(() {});
                          },
                        ),
                        const SizedBox(height: 10),
                        TextFormField(
                          controller: descController,
                          style: const TextStyle(fontSize: 13),
                          decoration: InputDecoration(
                            labelText: 'Keterangan',
                            labelStyle: const TextStyle(fontSize: 12),
                            hintText: 'Contoh: Beli bahan baku',
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                            filled: true,
                            isDense: true,
                            fillColor: colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                          ),
                          maxLines: 2,
                        ),
                        const SizedBox(height: 10),
                        TextFormField(
                          controller: amountController,
                          keyboardType: TextInputType.number,
                          inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[\d.,]'))],
                          style: const TextStyle(fontSize: 13),
                          decoration: InputDecoration(
                            labelText: 'Jumlah (Rp)',
                            labelStyle: const TextStyle(fontSize: 12),
                            hintText: '0',
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                            filled: true,
                            isDense: true,
                            fillColor: colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                          ),
                        ),
                        const SizedBox(height: 10),
                        Material(
                          color: Colors.transparent,
                          child: InkWell(
                            onTap: canPickExpenseDate
                                ? () async {
                                    final d = await showDatePicker(
                                      context: ctx,
                                      initialDate: expenseDate,
                                      firstDate: DateTime(2020),
                                      lastDate: DateTime.now(),
                                    );
                                    if (d != null) {
                                      expenseDate = DateTime(d.year, d.month, d.day);
                                      setModal(() {});
                                    }
                                  }
                                : null,
                            borderRadius: BorderRadius.circular(8),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                              decoration: BoxDecoration(
                                border: Border.all(
                                  color: colorScheme.outlineVariant.withValues(alpha: 0.5),
                                ),
                                borderRadius: BorderRadius.circular(8),
                                color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
                              ),
                              child: Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.all(6),
                                    decoration: BoxDecoration(
                                      color: AppColors.bluePrimary.withValues(alpha: 0.12),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Icon(
                                      canPickExpenseDate
                                          ? Icons.calendar_today_rounded
                                          : Icons.today_rounded,
                                      color: AppColors.bluePrimary,
                                      size: 16,
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          'Tanggal',
                                          style: theme.textTheme.bodySmall?.copyWith(
                                            color: colorScheme.onSurfaceVariant,
                                            fontSize: 11,
                                          ),
                                        ),
                                        const SizedBox(height: 1),
                                        Text(
                                          _formatDate(expenseDate),
                                          style: theme.textTheme.bodyMedium?.copyWith(
                                            fontWeight: FontWeight.w600,
                                            fontSize: 13,
                                          ),
                                        ),
                                        if (!canPickExpenseDate) ...[
                                          const SizedBox(height: 2),
                                          Text(
                                            'Hanya hari ini. Admin/owner dapat ubah tanggal.',
                                            style: theme.textTheme.bodySmall?.copyWith(
                                              color: colorScheme.onSurfaceVariant,
                                              fontSize: 10,
                                              height: 1.2,
                                            ),
                                          ),
                                        ],
                                      ],
                                    ),
                                  ),
                                  Icon(
                                    canPickExpenseDate
                                        ? Icons.arrow_forward_ios_rounded
                                        : Icons.lock_outline_rounded,
                                    size: 14,
                                    color: colorScheme.onSurfaceVariant.withValues(
                                      alpha: canPickExpenseDate ? 1 : 0.6,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 14),
                        Row(
                          children: [
                            Expanded(
                              child: OutlinedButton(
                                onPressed: () => Navigator.pop(ctx, false),
                                style: OutlinedButton.styleFrom(
                                  padding: const EdgeInsets.symmetric(vertical: 11),
                                  visualDensity: VisualDensity.compact,
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                ),
                                child: const Text('Batal', style: TextStyle(fontSize: 13)),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              flex: 2,
                              child: FilledButton.icon(
                                onPressed: () {
                                  if (descController.text.trim().isEmpty) {
                                    AppToast.show(
                                      ctx,
                                      'Keterangan wajib diisi',
                                      backgroundColor: AppColors.redCancel,
                                    );
                                    return;
                                  }
                                  final amount = double.tryParse(
                                    amountController.text.replaceAll(',', '.').replaceAll(' ', ''),
                                  );
                                  if (amount == null || amount <= 0) {
                                    AppToast.show(
                                      ctx,
                                      'Jumlah harus lebih dari 0',
                                      backgroundColor: AppColors.redCancel,
                                    );
                                    return;
                                  }
                                  Navigator.pop(ctx, true);
                                },
                                icon: const Icon(Icons.check_rounded, size: 17),
                                label: const Text('Simpan', style: TextStyle(fontSize: 13)),
                                style: FilledButton.styleFrom(
                                  backgroundColor: AppColors.greenSuccess,
                                  padding: const EdgeInsets.symmetric(vertical: 11),
                                  visualDensity: VisualDensity.compact,
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );

    if (result != true || !mounted) return;

    final amount = double.tryParse(
          amountController.text.replaceAll(',', '.'),
        ) ??
        0.0;
    if (amount <= 0) return;
    final dateToSend =
        auth.canBackdateDailyExpense ? expenseDate : _todayDateOnly();
    try {
      await auth.api.createExpense(
        storeId: auth.selectedStoreId,
        category: category!,
        description: descController.text.trim(),
        amount: amount,
        expenseDate: _formatDate(dateToSend),
      );
      if (mounted) {
        AppToast.show(
          context,
          'Pengeluaran berhasil dicatat',
          backgroundColor: AppColors.greenSuccess,
        );
        _load();
      }
    } catch (e) {
      if (mounted) {
        AppToast.show(
          context,
          e.toString().replaceAll('ApiException: ', ''),
          backgroundColor: AppColors.redCancel,
        );
      }
    }
  }

  String _formatCurrency(double value) {
    return NumberFormat.currency(
      locale: 'id_ID',
      symbol: 'Rp ',
      decimalDigits: 0,
    ).format(value);
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
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: const BoxDecoration(
              color: AppColors.bluePrimary,
              boxShadow: [
                BoxShadow(
                  color: Colors.black26,
                  blurRadius: 6,
                  offset: Offset(0, 1),
                ),
              ],
            ),
            child: SafeArea(
              bottom: false,
              child: Row(
                children: [
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    icon: const Icon(Icons.arrow_back_rounded, color: Colors.white, size: 22),
                    onPressed: () => Navigator.pop(context),
                  ),
                  Expanded(
                    child: Text(
                      'Pengeluaran Harian',
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 17,
                      ),
                    ),
                  ),
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    icon: const Icon(Icons.date_range_rounded, color: Colors.white, size: 22),
                    onPressed: _loading ? null : _pickDateRange,
                    tooltip: 'Pilih rentang tanggal',
                  ),
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    icon: const Icon(Icons.refresh_rounded, color: Colors.white, size: 22),
                    onPressed: _loading ? null : _load,
                  ),
                ],
              ),
            ),
          ),
          Expanded(
            child: _buildBody(isWide),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _loading ? null : _showAddExpense,
        icon: const Icon(Icons.add_rounded, size: 20),
        label: const Text(
          'Tambah',
          style: TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w600,
            fontSize: 13,
          ),
        ),
        backgroundColor: AppColors.bluePrimary,
        foregroundColor: Colors.white,
        extendedPadding: const EdgeInsets.symmetric(horizontal: 16),
      ),
    );
  }

  Widget _buildBody(bool isWide) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    if (_loading) {
      return Center(
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
              'Memuat data...',
              style: theme.textTheme.bodySmall?.copyWith(
                color: colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      );
    }

    if (_error != null) {
      return _buildError();
    }

    return RefreshIndicator(
      onRefresh: _load,
      color: AppColors.bluePrimary,
      child: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child: Container(
              margin: const EdgeInsets.fromLTRB(12, 10, 12, 4),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    AppColors.redCancel.withValues(alpha: 0.08),
                    AppColors.redCancel.withValues(alpha: 0.12),
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(
                  color: AppColors.redCancel.withValues(alpha: 0.2),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(6),
                        decoration: BoxDecoration(
                          color: AppColors.redCancel.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Icon(
                          Icons.calendar_month_rounded,
                          color: AppColors.redCancel,
                          size: 17,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          '${_formatDate(_dateFrom)} - ${_formatDate(_dateTo)}',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colorScheme.onSurface,
                            fontWeight: FontWeight.w500,
                            fontSize: 12,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Divider(
                    color: AppColors.redCancel.withValues(alpha: 0.2),
                    height: 1,
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.baseline,
                    textBaseline: TextBaseline.alphabetic,
                    children: [
                      Text(
                        'Total Pengeluaran',
                        style: theme.textTheme.labelLarge?.copyWith(
                          color: colorScheme.onSurfaceVariant,
                          fontWeight: FontWeight.w600,
                          fontSize: 12,
                        ),
                      ),
                      Text(
                        _formatCurrency(_totalAmount),
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w700,
                          color: AppColors.redCancel,
                          fontSize: 17,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          if (_expenses.isEmpty)
            SliverFillRemaining(
              hasScrollBody: false,
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.receipt_long_rounded,
                        size: 36,
                        color: colorScheme.onSurfaceVariant.withValues(alpha: 0.4),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Belum Ada Pengeluaran',
                      style: theme.textTheme.titleSmall?.copyWith(
                        color: colorScheme.onSurfaceVariant,
                        fontWeight: FontWeight.w600,
                        fontSize: 15,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Tap tombol + untuk menambahkan',
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
              padding: EdgeInsets.fromLTRB(
                12,
                4,
                12,
                isWide ? 12 : 72,
              ),
              sliver: SliverList(
                delegate: SliverChildBuilderDelegate(
                  (ctx, i) {
                    final e = _expenses[i];
                    return _ExpenseCard(
                      expense: e,
                      formatCurrency: _formatCurrency,
                    );
                  },
                  childCount: _expenses.length,
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildError() {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.error_outline_rounded,
              size: 48,
              color: colorScheme.error,
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
              _error!,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(
                color: colorScheme.onSurfaceVariant,
                fontSize: 13,
              ),
            ),
            const SizedBox(height: 18),
            FilledButton.icon(
              onPressed: _load,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text('Coba Lagi', style: TextStyle(fontSize: 13)),
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.bluePrimary,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                visualDensity: VisualDensity.compact,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ExpenseCard extends StatelessWidget {
  final DailyExpense expense;
  final String Function(double) formatCurrency;

  const _ExpenseCard({
    required this.expense,
    required this.formatCurrency,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    IconData icon;
    Color iconColor;

    switch (expense.category) {
      case 'operational':
        icon = Icons.business_center_rounded;
        iconColor = AppColors.orangeWarning;
        break;
      case 'stock':
        icon = Icons.inventory_2_rounded;
        iconColor = AppColors.bluePrimary;
        break;
      case 'salary':
        icon = Icons.account_balance_wallet_rounded;
        iconColor = AppColors.greenSuccess;
        break;
      case 'maintenance':
        icon = Icons.build_rounded;
        iconColor = Colors.purple;
        break;
      default:
        icon = Icons.payments_rounded;
        iconColor = AppColors.redCancel;
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        decoration: BoxDecoration(
          color: colorScheme.surface,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: colorScheme.outlineVariant.withValues(alpha: 0.3),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 4,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: iconColor.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(icon, color: iconColor, size: 20),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    expense.description,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                      height: 1.25,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 3),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 6,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: iconColor.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      '${expense.categoryLabel} • ${expense.expenseDate}',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: iconColor,
                        fontWeight: FontWeight.w500,
                        fontSize: 10,
                      ),
                    ),
                  ),
                  if (expense.creatorName != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      'oleh ${expense.creatorName}',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: colorScheme.onSurfaceVariant.withValues(alpha: 0.7),
                        fontSize: 10,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 8),
            Text(
              formatCurrency(expense.amount),
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w700,
                color: AppColors.redCancel,
                fontSize: 13,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
