import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
import '../storage/secure_storage_service.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  ApiException(this.message, {this.statusCode, this.errors});

  @override
  String toString() => message;
}

class ApiService {
  static VoidCallback? onSessionExpired;
  static String? activeToken;

  // Build standard headers with active bearer token and gym code
  static Future<Map<String, String>> _getHeaders({String? gymCode}) async {
    final headers = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Connection': 'close',
    };

    String? token = activeToken;
    if (token == null || token.isEmpty) {
      token = await SecureStorageService.getToken();
    }

    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
      headers['X-Auth-Token'] = token;
      headers['X-Bearer-Token'] = token;
    }

    final code = gymCode ?? await SecureStorageService.getCurrentGymCode();
    if (code != null && code.isNotEmpty) {
      headers['X-Gym-Code'] = code;
    }

    return headers;
  }

  // Resilient HTTP executor: handles keep-alive disconnects, edge proxy timeouts, and broken pipes
  static Future<http.Response> _execute(Future<http.Response> Function(http.Client client) action) async {
    http.Client client = http.Client();
    try {
      return await action(client);
    } catch (e) {
      client.close();
      // Auto-retry once on network/socket/write failure
      if (e is http.ClientException || e is SocketException || e.toString().contains('Write failed') || e.toString().contains('Broken pipe')) {
        client = http.Client();
        try {
          return await action(client);
        } finally {
          client.close();
        }
      }
      rethrow;
    } finally {
      client.close();
    }
  }

  // GET Request
  static Future<dynamic> get(
    String endpoint, {
    Map<String, String>? queryParams,
    String? gymCode,
    bool isAdmin = false,
  }) async {
    try {
      final base = isAdmin ? ApiConfig.adminBaseUrl : ApiConfig.baseUrl;
      var uri = Uri.parse('$base$endpoint');
      if (queryParams != null && queryParams.isNotEmpty) {
        uri = uri.replace(queryParameters: queryParams);
      }

      final headers = await _getHeaders(gymCode: gymCode);
      final response = await _execute((client) => client.get(uri, headers: headers).timeout(const Duration(seconds: 25)));
      return _handleResponse(response);
    } on SocketException {
      throw ApiException('No internet connection. Please check your network and try again.');
    } on TimeoutException {
      throw ApiException('Connection timed out. Please try again.');
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException('Network error: ${e.toString()}');
    }
  }

  // POST Request
  static Future<dynamic> post(
    String endpoint, {
    dynamic body,
    String? gymCode,
    bool isAdmin = false,
  }) async {
    try {
      final base = isAdmin ? ApiConfig.adminBaseUrl : ApiConfig.baseUrl;
      final uri = Uri.parse('$base$endpoint');
      final headers = await _getHeaders(gymCode: gymCode);
      final response = await _execute((client) => client
          .post(uri, headers: headers, body: body != null ? jsonEncode(body) : null)
          .timeout(const Duration(seconds: 25)));
      return _handleResponse(response);
    } on SocketException {
      throw ApiException('No internet connection. Please check your network and try again.');
    } on TimeoutException {
      throw ApiException('Connection timed out. Please try again.');
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException('Network error: ${e.toString()}');
    }
  }

  // Multipart POST Request (For Photo Uploads)
  static Future<dynamic> multipartPost(
    String endpoint, {
    required Map<String, String> fields,
    String? fileField,
    String? filePath,
    List<int>? fileBytes,
    String? fileName,
    String? gymCode,
    bool isAdmin = false,
  }) async {
    try {
      final base = isAdmin ? ApiConfig.adminBaseUrl : ApiConfig.baseUrl;
      final uri = Uri.parse('$base$endpoint');
      final request = http.MultipartRequest('POST', uri);

      final headers = await _getHeaders(gymCode: gymCode);
      headers.remove('Content-Type'); // Multipart boundary is set automatically
      request.headers.addAll(headers);
      request.fields.addAll(fields);

      if (fileField != null) {
        if (filePath != null && filePath.isNotEmpty && !kIsWeb) {
          request.files.add(await http.MultipartFile.fromPath(fileField, filePath));
        } else if (fileBytes != null && fileBytes.isNotEmpty) {
          request.files.add(http.MultipartFile.fromBytes(
            fileField,
            fileBytes,
            filename: fileName ?? 'photo.jpg',
          ));
        }
      }

      final streamedResponse = await request.send().timeout(const Duration(seconds: 30));
      final response = await http.Response.fromStream(streamedResponse);
      return _handleResponse(response);
    } on SocketException {
      throw ApiException('No internet connection. Please check your network and try again.');
    } on TimeoutException {
      throw ApiException('Connection timed out. Please try again.');
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException('Network error: ${e.toString()}');
    }
  }

  // Unified Response Parser
  static dynamic _handleResponse(http.Response response) {
    dynamic decodedBody;
    try {
      decodedBody = jsonDecode(response.body);
    } catch (_) {
      if (response.statusCode >= 500) {
        throw ApiException('Server error (${response.statusCode}). Please contact gym support.', statusCode: response.statusCode);
      }
      throw ApiException('Invalid response format received from server.', statusCode: response.statusCode);
    }

    final isSuccess = decodedBody is Map && decodedBody['success'] == true;
    final message = (decodedBody is Map && decodedBody['message'] != null)
        ? decodedBody['message'].toString()
        : 'Request failed.';

    if (response.statusCode == 401) {
      onSessionExpired?.call();
      throw ApiException(message, statusCode: 401);
    }

    if (response.statusCode == 403) {
      throw ApiException(message, statusCode: 403);
    }

    if (!isSuccess || response.statusCode >= 400) {
      Map<String, dynamic>? validationErrors;
      if (decodedBody is Map && decodedBody['errors'] is Map) {
        validationErrors = Map<String, dynamic>.from(decodedBody['errors']);
      }
      throw ApiException(message, statusCode: response.statusCode, errors: validationErrors);
    }

    return decodedBody['data'];
  }
}
