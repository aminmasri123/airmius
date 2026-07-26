/// Validates URLs before handing them to an external application.
///
/// API responses are untrusted input. In particular, `url_launcher` should
/// never receive custom schemes, empty hosts, or URLs containing embedded
/// credentials. Payment and document links use [httpsOnly]; public websites
/// may explicitly opt into regular HTTP for legacy pages.
Uri? safeExternalHttpUrl(String? raw, {bool httpsOnly = true}) {
  final value = raw?.trim();
  if (value == null || value.isEmpty) return null;

  final uri = Uri.tryParse(value);
  if (uri == null || uri.host.trim().isEmpty || uri.userInfo.isNotEmpty) {
    return null;
  }

  final scheme = uri.scheme.toLowerCase();
  if (httpsOnly ? scheme != 'https' : scheme != 'https' && scheme != 'http') {
    return null;
  }
  return uri;
}
