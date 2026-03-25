import 'package:shared_preferences/shared_preferences.dart';

class StorageService {
  static const _keyToken = 'auth_token';
  static const _keySelectedStoreId = 'selected_store_id';

  final SharedPreferences _prefs;

  StorageService(this._prefs);

  Future<void> saveToken(String token) async {
    await _prefs.setString(_keyToken, token);
  }

  String? getToken() {
    return _prefs.getString(_keyToken);
  }

  Future<void> clearToken() async {
    await _prefs.remove(_keyToken);
    await _prefs.remove(_keySelectedStoreId);
  }

  Future<void> saveSelectedStoreId(int storeId) async {
    await _prefs.setInt(_keySelectedStoreId, storeId);
  }

  int? getSelectedStoreId() {
    return _prefs.getInt(_keySelectedStoreId);
  }
}
