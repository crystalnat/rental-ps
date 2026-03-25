class DailyExpense {
  final int id;
  final String category;
  final String categoryLabel;
  final String description;
  final double amount;
  final String expenseDate;
  final String createdAt;
  final String? creatorName;

  DailyExpense({
    required this.id,
    required this.category,
    required this.categoryLabel,
    required this.description,
    required this.amount,
    required this.expenseDate,
    required this.createdAt,
    this.creatorName,
  });

  factory DailyExpense.fromJson(Map<String, dynamic> json) {
    return DailyExpense(
      id: json['id'] as int,
      category: json['category'] as String,
      categoryLabel: json['category_label'] as String? ?? json['category'] as String,
      description: json['description'] as String,
      amount: (json['amount'] as num).toDouble(),
      expenseDate: json['expense_date'] as String,
      createdAt: json['created_at'] as String,
      creatorName: json['creator_name'] as String?,
    );
  }
}
