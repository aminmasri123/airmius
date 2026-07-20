import 'dart:async';

import 'package:http/http.dart' as http;

import 'airmius_api_client.dart';

typedef AirmiusUploadAttempt<T> = Future<T> Function(int attempt);

class AirmiusUploadRetryPolicy {
  const AirmiusUploadRetryPolicy({
    this.maxAttempts = 3,
    this.baseDelay = const Duration(milliseconds: 400),
    this.maxDelay = const Duration(seconds: 2),
  });

  final int maxAttempts;
  final Duration baseDelay;
  final Duration maxDelay;

  Future<T> run<T>(AirmiusUploadAttempt<T> attempt) async {
    final attempts = maxAttempts < 1 ? 1 : maxAttempts;
    Object? lastError;
    StackTrace? lastStackTrace;

    for (var currentAttempt = 1; currentAttempt <= attempts; currentAttempt++) {
      try {
        return await attempt(currentAttempt);
      } catch (error, stackTrace) {
        lastError = error;
        lastStackTrace = stackTrace;
        if (currentAttempt >= attempts || !shouldRetry(error)) {
          Error.throwWithStackTrace(error, stackTrace);
        }
        await _sleep(_delayFor(currentAttempt));
      }
    }

    Error.throwWithStackTrace(lastError!, lastStackTrace!);
  }

  bool shouldRetry(Object error) {
    if (error is AirmiusApiException) {
      if (_isLocalFileReadError(error)) return false;
      return error.statusCode == 0 ||
          error.statusCode == 408 ||
          error.statusCode == 429 ||
          error.statusCode >= 500;
    }
    return error is TimeoutException || error is http.ClientException;
  }

  Duration _delayFor(int failedAttempt) {
    if (baseDelay <= Duration.zero) return Duration.zero;

    var multiplier = 1;
    for (var index = 1; index < failedAttempt; index++) {
      multiplier *= 2;
    }

    final delay = Duration(microseconds: baseDelay.inMicroseconds * multiplier);
    return delay > maxDelay ? maxDelay : delay;
  }

  Future<void> _sleep(Duration delay) {
    if (delay <= Duration.zero) return Future<void>.value();
    return Future<void>.delayed(delay);
  }

  bool _isLocalFileReadError(AirmiusApiException error) {
    final body = error.body.toLowerCase();
    return error.statusCode == 0 &&
        body.contains('datei') &&
        body.contains('nicht gelesen');
  }
}
