import 'dart:convert';

import '../database/db_helper.dart';
import '../models/category.dart';
import '../models/order.dart';
import '../models/payment_method.dart';
import '../models/product.dart';
import '../services/api_service.dart';

/// Repository offline-first: coba API dulu, simpan ke SQLite, fallback ke SQLite saat offline.
class PosRepository {
  final ApiService api;

  PosRepository(this.api);

  /// Produk: fetch dari API, simpan ke SQLite. Jika gagal, baca dari SQLite.
  Future<List<Product>> getProducts({int? storeId}) async {
    if (storeId == null) return [];
    try {
      final list = await api.getProducts(storeId: storeId);
      await DbHelper.saveProducts(storeId, list);
      return list;
    } catch (_) {
      return DbHelper.getProducts(storeId);
    }
  }

  /// Kategori: sama seperti produk.
  Future<List<Category>> getCategories() async {
    try {
      final list = await api.getCategories();
      await DbHelper.saveCategories(list);
      return list;
    } catch (_) {
      return DbHelper.getCategories();
    }
  }

  /// Metode pembayaran: sama.
  Future<List<PaymentMethod>> getPaymentMethods() async {
    try {
      final list = await api.getPaymentMethods();
      await DbHelper.savePaymentMethods(list);
      return list;
    } catch (_) {
      return DbHelper.getPaymentMethods();
    }
  }

  /// Buat order: coba API dulu. Jika gagal, simpan ke pending_orders untuk sync nanti.
  Future<OrderResult> createOrder({
    int? storeId,
    required String type,
    required List<Map<String, dynamic>> items,
    required String paymentMethod,
    double? cashReceived,
    required double total,
  }) async {
    if (storeId == null) {
      return OrderResult.failed('Store tidak dipilih.');
    }
    try {
      final order = await api.createOrder(
        storeId: storeId,
        type: type,
        items: items,
        paymentMethod: paymentMethod,
        cashReceived: cashReceived,
      );
      return OrderResult.success(order);
    } catch (e) {
      final localCode = 'OFF-${DateTime.now().millisecondsSinceEpoch}';
      await DbHelper.insertPendingOrder(
        storeId: storeId,
        orderType: type,
        items: items,
        paymentMethod: paymentMethod,
        cashReceived: cashReceived,
        total: total,
        localOrderCode: localCode,
      );
      return OrderResult.offlineQueued(localCode);
    }
  }

  /// Sinkronkan order yang antri (offline) ke server.
  Future<SyncResult> syncPendingOrders() async {
    final pending = await DbHelper.getPendingSyncOrders();
    if (pending.isEmpty) return SyncResult(0, 0);

    int success = 0;
    int failed = 0;
    for (final row in pending) {
      try {
        final items = (jsonDecode(row['items_json'] as String) as List)
            .map((e) => e as Map<String, dynamic>)
            .toList();
        final createdAtMs = row['created_at'] as int?;
        final createdAt = createdAtMs != null
            ? DateTime.fromMillisecondsSinceEpoch(createdAtMs).toUtc().toIso8601String()
            : null;
        final order = await api.createOrder(
          storeId: row['store_id'] as int,
          type: row['order_type'] as String,
          items: items,
          paymentMethod: row['payment_method'] as String,
          cashReceived: (row['cash_received'] as num?)?.toDouble(),
          createdAt: createdAt,
          paidAt: createdAt,
        );
        await DbHelper.markOrderSynced(
          row['id'] as int,
          order.id,
          order.orderCode,
        );
        success++;
      } catch (_) {
        await DbHelper.markOrderSyncFailed(row['id'] as int);
        failed++;
      }
    }
    return SyncResult(success, failed);
  }

  Future<int> getPendingOrdersCount() => DbHelper.getPendingOrdersCount();

  /// Ringkasan dari API + penjualan yang masih antre offline (hari ini, per toko).
  /// Jika API gagal, angka server = 0 tapi penjualan offline tetap ditambahkan; `summary_api_reachable` false.
  Future<Map<String, dynamic>> getSummaryWithOffline({int? storeId}) async {
    Map<String, dynamic> server = {
      'pending_orders_count': 0,
      'sales_today': 0.0,
      'orders_today': 0,
      'expenses_today': 0.0,
    };
    var apiOk = false;

    try {
      final r = await api.getSummary(storeId: storeId);
      server = Map<String, dynamic>.from(r);
      apiOk = true;
    } catch (_) {}

    double d(dynamic v) => (v is num) ? v.toDouble() : 0.0;
    int i(dynamic v) => (v is num) ? v.toInt() : 0;

    server['sales_today'] = d(server['sales_today']);
    server['orders_today'] = i(server['orders_today']);
    server['expenses_today'] = d(server['expenses_today']);
    server['pending_orders_count'] = i(server['pending_orders_count']);

    if (storeId != null) {
      final offline = await DbHelper.getTodayOfflineSalesStats(storeId);
      final offSales = offline['sales'] as double? ?? 0.0;
      final offOrders = offline['orders'] as int? ?? 0;
      server['sales_today'] = (server['sales_today'] as double) + offSales;
      server['orders_today'] = (server['orders_today'] as int) + offOrders;
      server['offline_sales_today'] = offSales;
      server['offline_orders_today'] = offOrders;
    } else {
      server['offline_sales_today'] = 0.0;
      server['offline_orders_today'] = 0;
    }

    server['summary_api_reachable'] = apiOk;
    return server;
  }
}

enum OrderResultType { success, offlineQueued, failed }

class OrderResult {
  final OrderResultType type;
  final Order? order;
  final String? localOrderCode;
  final String? message;

  OrderResult._(this.type, {this.order, this.localOrderCode, this.message});

  factory OrderResult.success(Order order) =>
      OrderResult._(OrderResultType.success, order: order);

  factory OrderResult.offlineQueued(String localCode) =>
      OrderResult._(OrderResultType.offlineQueued, localOrderCode: localCode);

  factory OrderResult.failed(String msg) =>
      OrderResult._(OrderResultType.failed, message: msg);
}

class SyncResult {
  final int successCount;
  final int failedCount;

  SyncResult(this.successCount, this.failedCount);
}
