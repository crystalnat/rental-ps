class Product {
  final int id;
  final String name;
  final int? categoryId;
  final String? categoryName;
  final String? categoryColor;
  final String unit;
  final bool trackStock;
  final double currentStock;
  final double sellPrice;
  final String? imageUrl;

  Product({
    required this.id,
    required this.name,
    this.categoryId,
    this.categoryName,
    this.categoryColor,
    required this.unit,
    required this.trackStock,
    required this.currentStock,
    required this.sellPrice,
    this.imageUrl,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: json['id'] as int,
      name: json['name'] as String,
      categoryId: json['category_id'] as int?,
      categoryName: json['category_name'] as String?,
      categoryColor: json['category_color'] as String?,
      unit: json['unit'] as String? ?? 'pcs',
      trackStock: json['track_stock'] as bool? ?? false,
      currentStock: (json['current_stock'] as num?)?.toDouble() ?? 0,
      sellPrice: (json['sell_price'] as num).toDouble(),
      imageUrl: json['image_url'] as String?,
    );
  }
}
