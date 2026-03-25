import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../models/category.dart';
import '../models/daily_expense.dart';
import '../models/order.dart';
import '../models/payment_method.dart';
import '../models/product.dart';
import '../models/user.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;

  ApiException(this.message, [this.statusCode]);

  @override
  String toString() => message;
}

class ApiService {
  final String baseUrl;
  String? _token;

  ApiService({String? baseUrl}) : baseUrl = baseUrl ?? apiBaseUrl;

  void setToken(String? token) {
    _token = token;
  }

  Map<String, String> get _headers {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (_token != null) {
      headers['Authorization'] = 'Bearer $_token';
    }
    return headers;
  }

  Future<Map<String, dynamic>> _handleResponse(http.Response response) async {
    final body = response.body.isEmpty ? {} : jsonDecode(response.body);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return body is Map<String, dynamic> ? body : {'data': body};
    }

    final message = body is Map && body['message'] != null
        ? body['message'] as String
        : 'Terjadi kesalahan (${response.statusCode})';
    throw ApiException(message, response.statusCode);
  }

  /// Test koneksi ke API (untuk debug emulator/device).
  Future<Map<String, dynamic>> testHealth() async {
    final response = await http.get(
      Uri.parse('$baseUrl/health'),
      headers: _headers,
    ).timeout(const Duration(seconds: 15));
    return _handleResponse(response);
  }

  // Auth
  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/auth/login'),
      headers: _headers,
      body: jsonEncode({'email': email, 'password': password}),
    );
    return _handleResponse(response);
  }

  Future<void> logout() async {
    await http.post(
      Uri.parse('$baseUrl/auth/logout'),
      headers: _headers,
    );
  }

  Future<User> getMe() async {
    final response = await http.get(
      Uri.parse('$baseUrl/auth/me'),
      headers: _headers,
    );
    final data = await _handleResponse(response);
    return User.fromJson(data['user'] as Map<String, dynamic>);
  }

  // Stores (untuk admin/owner pilih toko)
  Future<List<StoreInfo>> getStores() async {
    final response = await http.get(
      Uri.parse('$baseUrl/cashier/stores'),
      headers: _headers,
    );
    final data = await _handleResponse(response);
    return (data['stores'] as List)
        .map((e) => StoreInfo.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  /// Detail toko aktif (termasuk [StoreInfo.receiptPrintEnabled]).
  /// [storeId] wajib untuk owner/admin yang memilih toko.
  Future<StoreInfo> getCashierStore({int? storeId}) async {
    final uri = storeId != null
        ? Uri.parse('$baseUrl/cashier/store?store_id=$storeId')
        : Uri.parse('$baseUrl/cashier/store');
    final response = await http.get(uri, headers: _headers);
    final data = await _handleResponse(response);
    return StoreInfo.fromJson(data['store'] as Map<String, dynamic>);
  }

  // Categories
  Future<List<Category>> getCategories() async {
    final response = await http.get(
      Uri.parse('$baseUrl/cashier/categories'),
      headers: _headers,
    );
    final data = await _handleResponse(response);
    final list = data['categories'];
    if (list == null || list is! List) return [];
    return (list as List)
        .map((e) => Category.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  // Products
  Future<List<Product>> getProducts({int? storeId}) async {
    final uri = storeId != null
        ? Uri.parse('$baseUrl/cashier/products?store_id=$storeId')
        : Uri.parse('$baseUrl/cashier/products');
    final response = await http.get(uri, headers: _headers);
    final data = await _handleResponse(response);
    return (data['products'] as List)
        .map((e) => Product.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  // Payment methods
  Future<List<PaymentMethod>> getPaymentMethods() async {
    final response = await http.get(
      Uri.parse('$baseUrl/cashier/payment-methods'),
      headers: _headers,
    );
    final data = await _handleResponse(response);
    return (data['payment_methods'] as List)
        .map((e) => PaymentMethod.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  // Orders
  Future<Order> createOrder({
    int? storeId,
    required String type,
    int? tableId,
    String? customerName,
    String? customerPhone,
    String? customerEmail,
    required List<Map<String, dynamic>> items,
    String? notes,
    required String paymentMethod,
    double? cashReceived,
    String? createdAt,
    String? paidAt,
  }) async {
    final body = <String, dynamic>{
      'type': type,
      'items': items,
      'payment_method': paymentMethod,
    };
    if (storeId != null) body['store_id'] = storeId;
    if (tableId != null) body['table_id'] = tableId;
    if (customerName != null) body['customer_name'] = customerName;
    if (customerPhone != null) body['customer_phone'] = customerPhone;
    if (customerEmail != null) body['customer_email'] = customerEmail;
    if (notes != null) body['notes'] = notes;
    if (cashReceived != null) body['cash_received'] = cashReceived;
    if (createdAt != null) body['created_at'] = createdAt;
    if (paidAt != null) body['paid_at'] = paidAt;

    final response = await http.post(
      Uri.parse('$baseUrl/cashier/orders'),
      headers: _headers,
      body: jsonEncode(body),
    );
    final data = await _handleResponse(response);
    return Order.fromJson(data['order'] as Map<String, dynamic>);
  }

  Future<List<Order>> getPendingOrders({int? storeId}) async {
    final uri = storeId != null
        ? Uri.parse('$baseUrl/cashier/pending-orders?store_id=$storeId')
        : Uri.parse('$baseUrl/cashier/pending-orders');
    final response = await http.get(uri, headers: _headers);
    final data = await _handleResponse(response);
    return (data['orders'] as List)
        .map((e) => Order.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  /// Riwayat transaksi terbayar.
  /// Gunakan [days] (1, 7, atau 30) **atau** [dateFrom]+[dateTo] rentang **atau** [date] satu hari.
  Future<Map<String, dynamic>> getOrderHistory({
    int? storeId,
    int? days,
    String? dateFrom,
    String? dateTo,
    String? date,
    int page = 1,
    int perPage = 20,
  }) async {
    final query = <String, String>{
      'page': '$page',
      'per_page': '$perPage',
    };
    if (storeId != null) query['store_id'] = '$storeId';
    if (days != null) query['days'] = '$days';
    if (dateFrom != null && dateFrom.isNotEmpty) query['date_from'] = dateFrom;
    if (dateTo != null && dateTo.isNotEmpty) query['date_to'] = dateTo;
    if (date != null && date.isNotEmpty) query['date'] = date;
    final uri = Uri.parse('$baseUrl/cashier/orders').replace(queryParameters: query);
    final response = await http.get(uri, headers: _headers);
    return _handleResponse(response);
  }

  Future<Order> getOrder(int id) async {
    final response = await http.get(
      Uri.parse('$baseUrl/cashier/orders/$id'),
      headers: _headers,
    );
    final data = await _handleResponse(response);
    return Order.fromJson(data['order'] as Map<String, dynamic>);
  }

  Future<Order> payOrder(int id, String paymentMethod, {double? cashReceived}) async {
    final body = <String, dynamic>{
      'payment_method': paymentMethod,
    };
    if (cashReceived != null) body['cash_received'] = cashReceived;

    final response = await http.post(
      Uri.parse('$baseUrl/cashier/orders/$id/pay'),
      headers: _headers,
      body: jsonEncode(body),
    );
    final data = await _handleResponse(response);
    return Order.fromJson(data['order'] as Map<String, dynamic>);
  }

  // Pengeluaran harian
  Future<Map<String, dynamic>> getExpenses({
    int? storeId,
    String? dateFrom,
    String? dateTo,
  }) async {
    final query = <String>[];
    if (storeId != null) query.add('store_id=$storeId');
    if (dateFrom != null) query.add('date_from=$dateFrom');
    if (dateTo != null) query.add('date_to=$dateTo');
    final qs = query.isEmpty ? '' : '?${query.join('&')}';
    final response = await http.get(
      Uri.parse('$baseUrl/cashier/expenses$qs'),
      headers: _headers,
    );
    return _handleResponse(response);
  }

  Future<Map<String, dynamic>> createExpense({
    int? storeId,
    required String category,
    required String description,
    required double amount,
    required String expenseDate,
  }) async {
    final body = <String, dynamic>{
      'category': category,
      'description': description,
      'amount': amount,
      'expense_date': expenseDate,
    };
    if (storeId != null) body['store_id'] = storeId;
    final response = await http.post(
      Uri.parse('$baseUrl/cashier/expenses'),
      headers: _headers,
      body: jsonEncode(body),
    );
    return _handleResponse(response);
  }

  // Floor plan (denah meja)
  Future<Map<String, dynamic>> getFloorPlan({int? storeId}) async {
    final uri = storeId != null
        ? Uri.parse('$baseUrl/cashier/floor-plan?store_id=$storeId')
        : Uri.parse('$baseUrl/cashier/floor-plan');
    final response = await http.get(uri, headers: _headers);
    return _handleResponse(response);
  }

  // Summary
  Future<Map<String, dynamic>> getSummary({int? storeId}) async {
    final uri = storeId != null
        ? Uri.parse('$baseUrl/cashier/summary?store_id=$storeId')
        : Uri.parse('$baseUrl/cashier/summary');
    final response = await http.get(uri, headers: _headers);
    return _handleResponse(response);
  }
}
