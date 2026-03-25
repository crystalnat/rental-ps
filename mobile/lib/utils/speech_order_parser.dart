import '../models/product.dart';

/// Parsing & pencocokan produk dari teks ucapan — sama dengan
/// [resources/js/pages/Admin/Cashier/Index.vue] (`parseSpeechToItems`, `findBestProductMatch`).

const Map<String, int> _numWords = {
  'satu': 1,
  'dua': 2,
  'tiga': 3,
  'empat': 4,
  'lima': 5,
  'enam': 6,
  'tujuh': 7,
  'delapan': 8,
  'sembilan': 9,
  'sepuluh': 10,
};

final RegExp _fillerStart = RegExp(
  r'^(terus|dan|sama|lagi|yang|tolong|mau|minta|saya|mohon|kasih|porsi|gelas|piring|mangkok)\s+',
  caseSensitive: false,
);
final RegExp _fillerEnd = RegExp(
  r'\s+(terus|dan|sama|lagi|yang|tolong|mau|minta|porsi|gelas|piring|mangkok)$',
  caseSensitive: false,
);
final RegExp _splitBy = RegExp(r'\s+(dan|sama|lagi|,)\s+', caseSensitive: false);
final RegExp _splitNoise = RegExp(r'^(dan|sama|lagi|,)$', caseSensitive: false);

final RegExp _numEnd = RegExp(r'\s+(\d+)\s*$');
final RegExp _numStart = RegExp(r'^(\d+)\s+(.+)$');
final RegExp _wordNumStart = RegExp(
  r'^(satu|dua|tiga|empat|lima|enam|tujuh|delapan|sembilan|sepuluh)\s+(.+)$',
  caseSensitive: false,
);

class SpeechOrderItem {
  final String productNameLower;
  final int qty;

  const SpeechOrderItem({required this.productNameLower, required this.qty});
}

/// Setelah angka 1–2 digit (qty), spasi, lalu huruf → awal item berikutnya.
/// Menghindari memecah "100 gram …" (run digit > 2).
final RegExp _betweenQtyAndNextProduct = RegExp(
  r'(?<=\d)\s+(?=\p{L})',
  unicode: true,
);

/// Setelah bilang angka (satu…sepuluh), spasi, lalu huruf → item berikutnya.
final RegExp _betweenWordQtyAndNextProduct = RegExp(
  r'(?<=\b(?:satu|dua|tiga|empat|lima|enam|tujuh|delapan|sembilan|sepuluh)\b)\s+(?=\p{L})',
  caseSensitive: false,
  unicode: true,
);

List<String> _splitChainedDigitSegments(String t) {
  final segments = <String>[];
  var start = 0;
  for (final m in _betweenQtyAndNextProduct.allMatches(t)) {
    final fullBefore = t.substring(0, m.start);
    final digitRun = RegExp(r'\d+$').firstMatch(fullBefore);
    if (digitRun != null && digitRun.group(0)!.length <= 2) {
      segments.add(t.substring(start, m.start).trim());
      start = m.end;
    }
  }
  segments.add(t.substring(start).trim());
  return segments.where((s) => s.isNotEmpty).toList();
}

List<String> _splitChainedWordSegments(String t) {
  final segments = <String>[];
  var start = 0;
  for (final m in _betweenWordQtyAndNextProduct.allMatches(t)) {
    segments.add(t.substring(start, m.start).trim());
    start = m.end;
  }
  segments.add(t.substring(start).trim());
  return segments.where((s) => s.isNotEmpty).toList();
}

/// "roti bakar 1 esteh 5" (tanpa "dan") → ["roti bakar 1", "esteh 5"]
List<String> _expandChainedSegments(String part) {
  var segs = _splitChainedDigitSegments(part);
  segs = segs.expand(_splitChainedWordSegments).toList();
  return segs;
}

/// Memecah ucapan menjadi nama produk + qty (nama dalam huruf kecil).
List<SpeechOrderItem> parseSpeechToItems(String text) {
  final parts = text
      .split(_splitBy)
      .where((p) => p.isNotEmpty && !_splitNoise.hasMatch(p.trim()))
      .toList();
  final items = <SpeechOrderItem>[];

  for (var part in parts) {
    part = part.replaceFirst(_fillerStart, '').replaceFirst(_fillerEnd, '').trim();
    if (part.isEmpty) continue;

    final subparts = _expandChainedSegments(part);
    for (final raw in subparts) {
      var segment = raw.trim();
      if (segment.isEmpty) continue;

      late String productName;
      var qty = 1;

      final ne = _numEnd.firstMatch(segment);
      final ns = _numStart.firstMatch(segment);
      final wns = _wordNumStart.firstMatch(segment);

      if (ne != null) {
        productName = segment.replaceFirst(_numEnd, '').trim();
        qty = int.tryParse(ne.group(1) ?? '1') ?? 1;
      } else if (ns != null) {
        qty = int.tryParse(ns.group(1) ?? '1') ?? 1;
        productName = (ns.group(2) ?? '').trim();
      } else if (wns != null) {
        final w = wns.group(1)?.toLowerCase() ?? '';
        qty = _numWords[w] ?? 1;
        productName = (wns.group(2) ?? '').trim();
      } else {
        productName = segment;
      }

      if (productName.isNotEmpty && qty > 0) {
        items.add(SpeechOrderItem(productNameLower: productName.toLowerCase(), qty: qty));
      }
    }
  }

  return items;
}

String _compactLower(String s) => s.toLowerCase().replaceAll(RegExp(r'\s+'), '');

/// Skor pencocokan sama seperti Vue `findBestProductMatch`.
Product? findBestProductMatch(String productNameLower, List<Product> products) {
  final words = productNameLower.split(RegExp(r'\s+')).where((w) => w.isNotEmpty).toList();
  if (words.isEmpty) return null;

  Product? bestProduct;
  double bestScore = double.negativeInfinity;

  for (final p in products) {
    final name = p.name.toLowerCase();
    final cat = (p.categoryName ?? '').toLowerCase();
    final searchText = '$name $cat';

    final cq = _compactLower(productNameLower);
    final cn = _compactLower(name);

    double score;
    if (name.contains(productNameLower)) {
      score = 1000.0 + productNameLower.length;
    } else if (productNameLower.contains(name)) {
      score = 500.0 + name.length;
    } else if (cq.isNotEmpty &&
        cn.isNotEmpty &&
        (cq == cn || cn.contains(cq) || cq.contains(cn))) {
      // Ucapan "esteh" / "eskopi" vs nama "Es Teh" (tanpa spasi sama jika di-compact).
      score = 520.0 + cq.length.toDouble();
    } else {
      final matchCount = words.where((w) => searchText.contains(w)).length;
      if (matchCount == words.length) {
        score = 100 + matchCount * 10 - name.length / 100;
      } else if (matchCount > 0) {
        score = matchCount - name.length / 1000;
      } else {
        continue;
      }
    }

    if (score > bestScore) {
      bestScore = score;
      bestProduct = p;
    }
  }

  return bestProduct;
}
