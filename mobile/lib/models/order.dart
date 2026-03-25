class Order {
  final int id;
  final String orderCode;
  final String type;
  final String status;
  final String? createdAt;
  final double subtotal;
  final double discountAmount;
  final double taxAmount;
  final double finalAmount;
  final String? paymentMethod;
  final String paymentStatus;
  final double? cashReceived;
  final double? changeAmount;
  final String? paidAt;
  final TableInfo? table;
  final CustomerInfo? customer;
  final List<OrderItem> items;

  Order({
    required this.id,
    required this.orderCode,
    required this.type,
    required this.status,
    this.createdAt,
    required this.subtotal,
    required this.discountAmount,
    required this.taxAmount,
    required this.finalAmount,
    this.paymentMethod,
    required this.paymentStatus,
    this.cashReceived,
    this.changeAmount,
    this.paidAt,
    this.table,
    this.customer,
    required this.items,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    return Order(
      id: json['id'] as int,
      orderCode: json['order_code'] as String,
      type: json['type'] as String,
      status: json['status'] as String,
      createdAt: json['created_at'] as String?,
      subtotal: (json['subtotal'] as num).toDouble(),
      discountAmount: (json['discount_amount'] as num?)?.toDouble() ?? 0,
      taxAmount: (json['tax_amount'] as num?)?.toDouble() ?? 0,
      finalAmount: (json['final_amount'] as num).toDouble(),
      paymentMethod: json['payment_method'] as String?,
      paymentStatus: json['payment_status'] as String,
      cashReceived: (json['cash_received'] as num?)?.toDouble(),
      changeAmount: (json['change_amount'] as num?)?.toDouble(),
      paidAt: json['paid_at'] as String?,
      table: json['table'] != null
          ? TableInfo.fromJson(json['table'] as Map<String, dynamic>)
          : null,
      customer: json['customer'] != null
          ? CustomerInfo.fromJson(json['customer'] as Map<String, dynamic>)
          : null,
      items: (json['items'] as List<dynamic>?)
              ?.map((e) => OrderItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}

class TableInfo {
  final int id;
  final String name;

  TableInfo({required this.id, required this.name});

  factory TableInfo.fromJson(Map<String, dynamic> json) {
    return TableInfo(
      id: json['id'] as int,
      name: json['name'] as String,
    );
  }
}

class CustomerInfo {
  final int id;
  final String name;
  final String? email;
  final String? phone;

  CustomerInfo({
    required this.id,
    required this.name,
    this.email,
    this.phone,
  });

  factory CustomerInfo.fromJson(Map<String, dynamic> json) {
    return CustomerInfo(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String?,
      phone: json['phone'] as String?,
    );
  }
}

class OrderItem {
  final int productId;
  final String productName;
  final double quantity;
  final double unitPrice;
  final double subtotal;
  final String? notes;

  OrderItem({
    required this.productId,
    required this.productName,
    required this.quantity,
    required this.unitPrice,
    required this.subtotal,
    this.notes,
  });

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    return OrderItem(
      productId: json['product_id'] as int,
      productName: json['product_name'] as String,
      quantity: (json['quantity'] as num).toDouble(),
      unitPrice: (json['unit_price'] as num).toDouble(),
      subtotal: (json['subtotal'] as num).toDouble(),
      notes: json['notes'] as String?,
    );
  }
}
