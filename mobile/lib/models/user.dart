class User {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String role;
  final int? brandId;
  final int? storeId;
  final BrandInfo? brand;
  final StoreInfo? store;

  User({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    required this.role,
    this.brandId,
    this.storeId,
    this.brand,
    this.store,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
      phone: json['phone'] as String?,
      role: json['role'] as String,
      brandId: json['brand_id'] as int?,
      storeId: json['store_id'] as int?,
      brand: json['brand'] != null
          ? BrandInfo.fromJson(json['brand'] as Map<String, dynamic>)
          : null,
      store: json['store'] != null
          ? StoreInfo.fromJson(json['store'] as Map<String, dynamic>)
          : null,
    );
  }
}

class BrandInfo {
  final int id;
  final String name;

  BrandInfo({required this.id, required this.name});

  factory BrandInfo.fromJson(Map<String, dynamic> json) {
    return BrandInfo(
      id: json['id'] as int,
      name: json['name'] as String,
    );
  }
}

class StoreInfo {
  final int id;
  final String name;
  final String slug;
  /// Admin: pengaturan per toko — jika true, kasir boleh pilih cetak struk.
  final bool receiptPrintEnabled;
  final String? address;
  final String? city;
  final String? phone;
  final String? openTime;
  final String? closeTime;

  StoreInfo({
    required this.id,
    required this.name,
    required this.slug,
    this.receiptPrintEnabled = false,
    this.address,
    this.city,
    this.phone,
    this.openTime,
    this.closeTime,
  });

  factory StoreInfo.fromJson(Map<String, dynamic> json) {
    return StoreInfo(
      id: json['id'] as int,
      name: json['name'] as String,
      slug: json['slug'] as String,
      receiptPrintEnabled: json['receipt_print_enabled'] == true,
      address: json['address'] as String?,
      city: json['city'] as String?,
      phone: json['phone'] as String?,
      openTime: json['open_time'] as String?,
      closeTime: json['close_time'] as String?,
    );
  }
}
