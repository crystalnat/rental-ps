import 'package:flutter/foundation.dart';

import '../models/user.dart';
import '../repositories/pos_repository.dart';
import '../services/api_service.dart';
import '../services/storage_service.dart';

class AuthProvider with ChangeNotifier {
  final ApiService _api = ApiService();
  final StorageService _storage;
  late final PosRepository posRepository;

  User? _user;
  StoreInfo? _selectedStore;
  bool _isLoading = false;
  String? _error;

  AuthProvider(StorageService storage) : _storage = storage {
    posRepository = PosRepository(_api);
    _init();
  }

  User? get user => _user;
  StoreInfo? get selectedStore => _selectedStore;
  int? get selectedStoreId => _selectedStore?.id;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isAuthenticated => _user != null;

  /// Admin/owner atau kasir tanpa toko harus pilih toko dulu sebelum ngasir.
  bool get needsStoreSelection =>
      _user != null &&
      ((_user!.role == 'owner' || _user!.role == 'admin') || (_user!.role == 'cashier' && _user!.store == null));

  /// Kasir punya toko tetap, admin/owner pilih manual.
  bool get hasStoreContext => _selectedStore != null;

  /// Admin/owner boleh mencatat pengeluaran untuk tanggal lain; kasir/staff hanya hari ini.
  bool get canBackdateDailyExpense =>
      _user != null && (_user!.role == 'admin' || _user!.role == 'owner');

  Future<void> _init() async {
    final token = _storage.getToken();
    if (token != null) {
      _api.setToken(token);
      try {
        _user = await _api.getMe();
        await _resolveStoreContext();
        notifyListeners();
      } catch (_) {
        await logout();
      }
    }
  }

  Future<void> _resolveStoreContext() async {
    if (_user == null) return;
    if (_user!.role == 'cashier' && _user!.store != null) {
      _selectedStore = _user!.store;
      return;
    }
    if (needsStoreSelection) {
      final savedId = _storage.getSelectedStoreId();
      if (savedId != null) {
        try {
          final stores = await _api.getStores();
        StoreInfo? match;
        for (final s in stores) {
          if (s.id == savedId) {
            match = s;
            break;
          }
        }
        if (match != null) {
            _selectedStore = match;
            return;
          }
        } catch (_) {}
      }
      _selectedStore = null;
    }
  }

  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final data = await _api.login(email, password);
      final token = data['token'] as String;
      _storage.saveToken(token);
      _api.setToken(token);
      _user = User.fromJson(data['user'] as Map<String, dynamic>);
      await _resolveStoreContext();
      _isLoading = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _error = e.message;
      _isLoading = false;
      notifyListeners();
      return false;
    } catch (e) {
      _error = 'Koneksi gagal. Periksa URL API.';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> setSelectedStore(StoreInfo store) async {
    _selectedStore = store;
    await _storage.saveSelectedStoreId(store.id);
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      await _api.logout();
    } catch (_) {}
    _storage.clearToken();
    _api.setToken(null);
    _user = null;
    _selectedStore = null;
    _error = null;
    notifyListeners();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  ApiService get api => _api;
}
