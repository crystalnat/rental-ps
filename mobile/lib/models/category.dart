class Category {
  final int id;
  final String name;
  final String slug;
  final String? icon;
  final String? color;
  final int sortOrder;

  Category({
    required this.id,
    required this.name,
    required this.slug,
    this.icon,
    this.color,
    this.sortOrder = 0,
  });

  factory Category.fromJson(Map<String, dynamic> json) {
    return Category(
      id: json['id'] as int,
      name: json['name'] as String,
      slug: json['slug'] as String,
      icon: json['icon'] as String?,
      color: json['color'] as String?,
      sortOrder: json['sort_order'] as int? ?? 0,
    );
  }
}
