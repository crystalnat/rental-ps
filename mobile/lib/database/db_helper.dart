import 'dart:convert';

import 'package:path/path.dart';
import 'package:sqflite/sqflite.dart';

import '../models/category.dart';
import '../models/payment_method.dart';
import '../models/product.dart';

class DbHelper {
  static Database? _db;
  static const _dbName = 'pos_kasir.db';
  static const _dbVersion = 1;

  static Future<Database> get database async {
    if (_db != null) return _db!;
    _db = await _initDb();
    return _db!;
  }

  static Future<Database> _initDb() async {
    final dbPath = await getDatabasesPath();
    final path = join(dbPath, _dbName);
    return openDatabase(
      path,
      version: _dbVersion,
      onCreate: _onCreate,
    );
  }

  static Future<void> _onCreate(Database db, int version) async {
    await db.execute('''
      CREATE TABLE products (
        id INTEGER PRIMARY KEY,
        store_id INTEGER,
        name TEXT NOT NULL,
        category_id INTEGER,
        category_name TEXT,
        category_color TEXT,
        unit TEXT,
        track_stock INTEGER,
        current_stock REAL,
        sell_price REAL NOT NULL,
        image_url TEXT,
        updated_at INTEGER
      )
    ''');
    await db.execute('''
      CREATE TABLE categories (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        slug TEXT,
        color TEXT,
        sort_order INTEGER,
        updated_at INTEGER
      )
    ''');
    await db.execute('''
      CREATE TABLE payment_methods (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        code TEXT NOT NULL,
        requires_cash_input INTEGER,
        updated_at INTEGER
      )
    ''');
    await db.execute('''
      CREATE TABLE pending_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        store_id INTEGER NOT NULL,
        order_type TEXT NOT NULL,
        items_json TEXT NOT NULL,
        payment_method TEXT NOT NULL,
        cash_received REAL,
        total REAL NOT NULL,
        local_order_code TEXT,
        server_order_id INTEGER,
        server_order_code TEXT,
        sync_status TEXT DEFAULT 'pending',
        created_at INTEGER NOT NULL,
        synced_at INTEGER
      )
    ''');
  }

  // Products
  static Future<void> saveProducts(int storeId, List<Product> products) async {
    final db = await database;
    await db.delete('products', where: 'store_id = ?', whereArgs: [storeId]);
    final now = DateTime.now().millisecondsSinceEpoch;
    for (final p in products) {
      await db.insert('products', {
        'id': p.id,
        'store_id': storeId,
        'name': p.name,
        'category_id': p.categoryId,
        'category_name': p.categoryName,
        'category_color': p.categoryColor,
        'unit': p.unit,
        'track_stock': p.trackStock ? 1 : 0,
        'current_stock': p.currentStock,
        'sell_price': p.sellPrice,
        'image_url': p.imageUrl,
        'updated_at': now,
      });
    }
  }

  static Future<List<Product>> getProducts(int storeId) async {
    final db = await database;
    final maps = await db.query('products', where: 'store_id = ?', whereArgs: [storeId]);
    return maps.map(_productFromMap).toList();
  }

  static Product _productFromMap(Map<String, dynamic> m) {
    return Product(
      id: m['id'] as int,
      name: m['name'] as String,
      categoryId: m['category_id'] as int?,
      categoryName: m['category_name'] as String?,
      categoryColor: m['category_color'] as String?,
      unit: m['unit'] as String? ?? 'pcs',
      trackStock: (m['track_stock'] as int?) == 1,
      currentStock: (m['current_stock'] as num?)?.toDouble() ?? 0,
      sellPrice: (m['sell_price'] as num).toDouble(),
      imageUrl: m['image_url'] as String?,
    );
  }

  // Categories
  static Future<void> saveCategories(List<Category> categories) async {
    final db = await database;
    await db.delete('categories');
    final now = DateTime.now().millisecondsSinceEpoch;
    for (final c in categories) {
      await db.insert('categories', {
        'id': c.id,
        'name': c.name,
        'slug': c.slug,
        'color': c.color,
        'sort_order': c.sortOrder,
        'updated_at': now,
      });
    }
  }

  static Future<List<Category>> getCategories() async {
    final db = await database;
    final maps = await db.query('categories', orderBy: 'sort_order, name');
    return maps.map(_categoryFromMap).toList();
  }

  static Category _categoryFromMap(Map<String, dynamic> m) {
    return Category(
      id: m['id'] as int,
      name: m['name'] as String,
      slug: m['slug'] as String,
      color: m['color'] as String?,
      sortOrder: m['sort_order'] as int? ?? 0,
    );
  }

  // Payment methods
  static Future<void> savePaymentMethods(List<PaymentMethod> methods) async {
    final db = await database;
    await db.delete('payment_methods');
    final now = DateTime.now().millisecondsSinceEpoch;
    for (final m in methods) {
      await db.insert('payment_methods', {
        'id': m.id,
        'name': m.name,
        'code': m.code,
        'requires_cash_input': m.requiresCashInput ? 1 : 0,
        'updated_at': now,
      });
    }
  }

  static Future<List<PaymentMethod>> getPaymentMethods() async {
    final db = await database;
    final maps = await db.query('payment_methods');
    return maps.map(_paymentMethodFromMap).toList();
  }

  static PaymentMethod _paymentMethodFromMap(Map<String, dynamic> m) {
    return PaymentMethod(
      id: m['id'] as int,
      name: m['name'] as String,
      code: m['code'] as String,
      requiresCashInput: (m['requires_cash_input'] as int?) == 1,
    );
  }

  // Pending orders (offline queue)
  static Future<int> insertPendingOrder({
    required int storeId,
    required String orderType,
    required List<Map<String, dynamic>> items,
    required String paymentMethod,
    double? cashReceived,
    required double total,
    required String localOrderCode,
  }) async {
    final db = await database;
    return db.insert('pending_orders', {
      'store_id': storeId,
      'order_type': orderType,
      'items_json': jsonEncode(items),
      'payment_method': paymentMethod,
      'cash_received': cashReceived,
      'total': total,
      'local_order_code': localOrderCode,
      'sync_status': 'pending',
      'created_at': DateTime.now().millisecondsSinceEpoch,
    });
  }

  static Future<List<Map<String, dynamic>>> getPendingSyncOrders() async {
    final db = await database;
    return db.query(
      'pending_orders',
      where: "sync_status = 'pending'",
      orderBy: 'created_at ASC',
    );
  }

  static Future<void> markOrderSynced(int id, int serverOrderId, String serverOrderCode) async {
    final db = await database;
    await db.update(
      'pending_orders',
      {
        'sync_status': 'synced',
        'server_order_id': serverOrderId,
        'server_order_code': serverOrderCode,
        'synced_at': DateTime.now().millisecondsSinceEpoch,
      },
      where: 'id = ?',
      whereArgs: [id],
    );
  }

  static Future<void> markOrderSyncFailed(int id) async {
    final db = await database;
    await db.update(
      'pending_orders',
      {'sync_status': 'failed'},
      where: 'id = ?',
      whereArgs: [id],
    );
  }

  static Future<void> deleteSyncedOrder(int id) async {
    final db = await database;
    await db.delete('pending_orders', where: 'id = ? AND sync_status = ?', whereArgs: [id, 'synced']);
  }

  static Future<int> getPendingOrdersCount() async {
    final db = await database;
    final r = await db.rawQuery(
      "SELECT COUNT(*) as c FROM pending_orders WHERE sync_status = 'pending'",
    );
    return r.first['c'] as int? ?? 0;
  }

  /// Transaksi lokal (belum / gagal sinkron) untuk riwayat offline — filter [created_at] waktu lokal.
  /// Jika [rangeStart] & [rangeEnd] diisi, dipakai rentang tanggal inklusif; jika tidak, [days] hari gulir (≥1).
  static Future<List<Map<String, dynamic>>> getOfflineLocalOrdersForHistory(
    int storeId, {
    required int days,
    DateTime? rangeStart,
    DateTime? rangeEnd,
  }) async {
    final db = await database;
    late int startMs;
    late int endMs;

    if (rangeStart != null && rangeEnd != null) {
      final a = rangeStart.isBefore(rangeEnd) ? rangeStart : rangeEnd;
      final b = rangeStart.isBefore(rangeEnd) ? rangeEnd : rangeStart;
      startMs = DateTime(a.year, a.month, a.day).millisecondsSinceEpoch;
      endMs = DateTime(b.year, b.month, b.day, 23, 59, 59, 999).millisecondsSinceEpoch;
    } else {
      final d = days < 1 ? 1 : days;
      final now = DateTime.now();
      endMs = DateTime(now.year, now.month, now.day, 23, 59, 59, 999).millisecondsSinceEpoch;
      final startDay = now.subtract(Duration(days: d - 1));
      startMs = DateTime(startDay.year, startDay.month, startDay.day).millisecondsSinceEpoch;
    }

    return db.query(
      'pending_orders',
      where:
          'store_id = ? AND sync_status IN (?, ?) AND created_at >= ? AND created_at <= ?',
      whereArgs: [storeId, 'pending', 'failed', startMs, endMs],
      orderBy: 'created_at DESC',
    );
  }

  /// Total & jumlah order offline (belum tersinkron) untuk toko ini, tanggal hari ini (waktu lokal).
  static Future<Map<String, dynamic>> getTodayOfflineSalesStats(int storeId) async {
    final db = await database;
    final now = DateTime.now();
    final start = DateTime(now.year, now.month, now.day).millisecondsSinceEpoch;
    final end = start + const Duration(days: 1).inMilliseconds;
    final r = await db.rawQuery(
      '''
      SELECT COALESCE(SUM(total), 0) as s, COUNT(*) as c
      FROM pending_orders
      WHERE store_id = ? AND sync_status = 'pending'
        AND created_at >= ? AND created_at < ?
      ''',
      [storeId, start, end],
    );
    final row = r.first;
    return {
      'sales': (row['s'] as num?)?.toDouble() ?? 0.0,
      'orders': (row['c'] as num?)?.toInt() ?? 0,
    };
  }
}
