import 'dart:typed_data';

import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;

import '../models/order.dart';

/// Struk thermal 80mm — format pasaran kasir.
class ReceiptPdf {
  ReceiptPdf._();

  // Full-width thermal 80mm (~42 chars)
  static const _sep = '===================================================';
  static const _sepThin = '-----------------------------------------------------------------------------------------';

  static Future<Uint8List> build({
    required String storeName,
    required Order order,
    required String paymentMethodLabel,
    required String Function(double) formatRp,
    String? storeAddress,
    String? storeCity,
    String? storePhone,
  }) async {
    final doc = pw.Document();

    String dateStr = 'N/A';
    if (order.createdAt != null) {
      final dt = DateTime.tryParse(order.createdAt!)?.toLocal() ?? DateTime.now();
      dateStr =
          '${dt.day.toString().padLeft(2, '0')}/${dt.month.toString().padLeft(2, '0')}/${dt.year} '
          '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    }

    doc.addPage(
      pw.Page(
        pageFormat: PdfPageFormat(
          80 * PdfPageFormat.mm,
          double.infinity,
          marginLeft: 3 * PdfPageFormat.mm,
          marginRight: 3 * PdfPageFormat.mm,
          marginTop: 5 * PdfPageFormat.mm,
          marginBottom: 6 * PdfPageFormat.mm,
        ),
        build: (pw.Context ctx) {
          return pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.stretch,
            children: [
              // Header
              pw.Center(
                child: pw.Text(_sep, style: const pw.TextStyle(fontSize: 7)),
              ),
              pw.SizedBox(height: 6),
              pw.Center(
                child: pw.Text(
                  storeName,
                  style: pw.TextStyle(
                    fontSize: 12,
                    fontWeight: pw.FontWeight.bold,
                  ),
                  textAlign: pw.TextAlign.center,
                  maxLines: 2,
                  overflow: pw.TextOverflow.clip,
                ),
              ),
              if (_hasStoreInfo(storeAddress, storeCity, storePhone)) ...[
                pw.SizedBox(height: 4),
                pw.Center(
                  child: pw.Text(
                    _buildStoreAddress(storeAddress, storeCity),
                    style: const pw.TextStyle(fontSize: 7),
                    textAlign: pw.TextAlign.center,
                    maxLines: 2,
                    overflow: pw.TextOverflow.clip,
                  ),
                ),
                if (storePhone != null && storePhone.isNotEmpty) ...[
                  pw.SizedBox(height: 2),
                  pw.Center(
                    child: pw.Text('Telp: $storePhone', style: const pw.TextStyle(fontSize: 7)),
                  ),
                ],
              ],
              pw.SizedBox(height: 4),
              pw.Center(
                child: pw.Text(
                  'STRUK PEMBAYARAN',
                  style: pw.TextStyle(
                    fontSize: 8,
                    fontWeight: pw.FontWeight.bold,
                  ),
                ),
              ),
              pw.SizedBox(height: 6),
              pw.Center(
                child: pw.Text(_sep, style: const pw.TextStyle(fontSize: 7)),
              ),
              pw.SizedBox(height: 6),

              // No order & tanggal
              pw.Row(
                mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                children: [
                  pw.Text('No. ${order.orderCode}', style: const pw.TextStyle(fontSize: 8)),
                  pw.Text('Tgl: $dateStr', style: const pw.TextStyle(fontSize: 8)),
                ],
              ),
              pw.SizedBox(height: 8),
              pw.Center(
                child: pw.Text(_sepThin, style: const pw.TextStyle(fontSize: 7)),
              ),
              pw.SizedBox(height: 6),

              // Daftar item: qty | nama | harga
              pw.Table(
                columnWidths: {
                  0: const pw.FixedColumnWidth(12),
                  1: const pw.FlexColumnWidth(1),
                  2: const pw.FixedColumnWidth(50),
                },
                children: order.items.map((i) {
                  return pw.TableRow(
                    children: [
                      pw.Padding(
                        padding: const pw.EdgeInsets.only(bottom: 6),
                        child: pw.Text(
                          _qtyStr(i.quantity),
                          style: const pw.TextStyle(fontSize: 8),
                        ),
                      ),
                      pw.Padding(
                        padding: const pw.EdgeInsets.only(bottom: 6),
                        child: pw.Text(
                          i.productName,
                          style: pw.TextStyle(
                            fontSize: 8,
                            fontWeight: pw.FontWeight.bold,
                          ),
                        ),
                      ),
                      pw.Padding(
                        padding: const pw.EdgeInsets.only(bottom: 6),
                        child: pw.Text(
                          'Rp ${formatRp(i.subtotal)}',
                          style: pw.TextStyle(
                            fontSize: 8,
                            fontWeight: pw.FontWeight.bold,
                          ),
                          textAlign: pw.TextAlign.right,
                        ),
                      ),
                    ],
                  );
                }).toList(),
              ),

              pw.Center(
                child: pw.Text(_sepThin, style: const pw.TextStyle(fontSize: 7)),
              ),
              pw.SizedBox(height: 6),

              // Subtotal & diskon (jika ada)
              if (order.discountAmount > 0) ...[
                pw.Table(
                  columnWidths: {
                    0: const pw.FlexColumnWidth(1),
                    1: const pw.FixedColumnWidth(50),
                  },
                  children: [
                    pw.TableRow(
                      children: [
                        pw.Text('Subtotal', style: const pw.TextStyle(fontSize: 8)),
                        pw.Text(
                          'Rp ${formatRp(order.subtotal)}',
                          style: const pw.TextStyle(fontSize: 8),
                          textAlign: pw.TextAlign.right,
                        ),
                      ],
                    ),
                    pw.TableRow(
                      children: [
                        pw.Text('Diskon', style: const pw.TextStyle(fontSize: 8)),
                        pw.Text(
                          '- Rp ${formatRp(order.discountAmount)}',
                          style: const pw.TextStyle(fontSize: 8),
                          textAlign: pw.TextAlign.right,
                        ),
                      ],
                    ),
                  ],
                ),
                pw.SizedBox(height: 4),
              ],

              // Total
              pw.Table(
                columnWidths: {
                  0: const pw.FlexColumnWidth(1),
                  1: const pw.FixedColumnWidth(50),
                },
                children: [
                  pw.TableRow(
                    children: [
                      pw.Text(
                        'TOTAL',
                        style: pw.TextStyle(
                          fontSize: 10,
                          fontWeight: pw.FontWeight.bold,
                        ),
                      ),
                      pw.Text(
                        'Rp ${formatRp(order.finalAmount)}',
                        style: pw.TextStyle(
                          fontSize: 10,
                          fontWeight: pw.FontWeight.bold,
                        ),
                        textAlign: pw.TextAlign.right,
                      ),
                    ],
                  ),
                ],
              ),
              pw.SizedBox(height: 6),

              // Pembayaran
              pw.Table(
                columnWidths: {
                  0: const pw.FlexColumnWidth(1),
                  1: const pw.FixedColumnWidth(50),
                },
                children: [
                  pw.TableRow(
                    children: [
                      pw.Text('Metode', style: const pw.TextStyle(fontSize: 8)),
                      pw.Text(
                        paymentMethodLabel,
                        style: const pw.TextStyle(fontSize: 8),
                        textAlign: pw.TextAlign.right,
                      ),
                    ],
                  ),
                  if (order.cashReceived != null)
                    pw.TableRow(
                      children: [
                        pw.Text('Tunai', style: const pw.TextStyle(fontSize: 8)),
                        pw.Text(
                          'Rp ${formatRp(order.cashReceived!)}',
                          style: const pw.TextStyle(fontSize: 8),
                          textAlign: pw.TextAlign.right,
                        ),
                      ],
                    ),
                  if (order.changeAmount != null && order.changeAmount! > 0)
                    pw.TableRow(
                      children: [
                        pw.Text('Kembali', style: const pw.TextStyle(fontSize: 8)),
                        pw.Text(
                          'Rp ${formatRp(order.changeAmount!)}',
                          style: const pw.TextStyle(fontSize: 8),
                          textAlign: pw.TextAlign.right,
                        ),
                      ],
                    ),
                ],
              ),
              pw.SizedBox(height: 10),

              // Footer
              pw.Center(
                child: pw.Text(_sep, style: const pw.TextStyle(fontSize: 7)),
              ),
              pw.SizedBox(height: 8),
              pw.Center(
                child: pw.Text(
                  'Terima kasih',
                  style: pw.TextStyle(
                    fontSize: 9,
                    fontWeight: pw.FontWeight.bold,
                  ),
                ),
              ),
              pw.SizedBox(height: 2),
              pw.Center(
                child: pw.Text(
                  'Selamat menikmati',
                  style: const pw.TextStyle(fontSize: 7),
                ),
              ),
              pw.SizedBox(height: 8),
              pw.Center(
                child: pw.Text(_sep, style: const pw.TextStyle(fontSize: 7)),
              ),
            ],
          );
        },
      ),
    );

    return doc.save();
  }

  static String _qtyStr(double q) {
    if (q == q.roundToDouble()) return q.toInt().toString();
    return q.toString();
  }

  static bool _hasStoreInfo(String? addr, String? city, String? phone) {
    final hasAddr = addr != null && addr.trim().isNotEmpty;
    final hasCity = city != null && city.trim().isNotEmpty;
    final hasPhone = phone != null && phone.trim().isNotEmpty;
    return hasAddr || hasCity || hasPhone;
  }

  static String _buildStoreAddress(String? address, String? city) {
    final a = address?.trim() ?? '';
    final c = city?.trim() ?? '';
    if (a.isEmpty && c.isEmpty) return '';
    if (a.isEmpty) return c;
    if (c.isEmpty) return a;
    return '$a, $c';
  }
}
