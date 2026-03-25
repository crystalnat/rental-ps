class PaymentMethod {
  final int id;
  final String name;
  final String code;
  final bool requiresCashInput;
  final String? qrcodeImage;

  PaymentMethod({
    required this.id,
    required this.name,
    required this.code,
    required this.requiresCashInput,
    this.qrcodeImage,
  });

  factory PaymentMethod.fromJson(Map<String, dynamic> json) {
    return PaymentMethod(
      id: json['id'] as int,
      name: json['name'] as String,
      code: json['code'] as String,
      requiresCashInput: json['requires_cash_input'] as bool? ?? false,
      qrcodeImage: json['qrcode_image'] as String?,
    );
  }
}
