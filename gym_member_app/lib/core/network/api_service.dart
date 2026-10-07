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
  static final http.Client _client = http.Client();
  static VoidCallback? onSessionExpired;

  // Build standard headers with active bearer token and gym code
  static Future<Map<String, String>> _getHeaders({String? gymCode, bool isMultipart = false}) async {
    final headers = <String, String>{
      'Accept': 'application/json',
    };
    if (!isMultipart) {
      headers['Content-Type'] = 'application/json';
    }

    final token = await SecureStorageService.getToken();
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }

    final code = gymCode ?? await SecureStorageService.getCurrentGymCode();
    if (code != null && code.isNotEmpty) {
      headers['X-Gym-Code'] = code;
    }

    return headers;
  }

  // GET Request (Member)
  static Future<dynamic> get(String endpoint, {Map<String, String>? queryParams, String? gymCode}) async {
    try {
      var uri = Uri.parse('${ApiConfig.baseUrl}$endpoint');
      if (queryParams != null && queryParams.isNotEmpty) {
        uri = uri.replace(queryParameters: queryParams);
      }

      final headers = await _getHeaders(gymCode: gymCode);
      final response = await _client.get(uri, headers: headers).timeout(const Duration(seconds: 15));
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

  // POST Request (Member)
  static Future<dynamic> post(String endpoint, {dynamic body, String? gymCode}) async {
    try {
      final uri = Uri.parse('${ApiConfig.baseUrl}$endpoint');
      final headers = await _getHeaders(gymCode: gymCode);
      final response = await _client
          .post(uri, headers: headers, body: body != null ? jsonEncode(body) : null)
          .timeout(const Duration(seconds: 15));
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

  // GET Request (Admin)
  static Future<dynamic> adminGet(String endpoint, {Map<String, String>? queryParams, String? gymCode}) async {
    try {
      var uri = Uri.parse('${ApiConfig.adminBaseUrl}$endpoint');
      if (queryParams != null && queryParams.isNotEmpty) {
        uri = uri.replace(queryParameters: queryParams);
      }

      final headers = await _getHeaders(gymCode: gymCode);
      final response = await _client.get(uri, headers: headers).timeout(const Duration(seconds: 15));
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

  // POST Request (Admin)
  static Future<dynamic> adminPost(String endpoint, {dynamic body, String? gymCode}) async {
    try {
      final uri = Uri.parse('${ApiConfig.adminBaseUrl}$endpoint');
      final headers = await _getHeaders(gymCode: gymCode);
      final response = await _client
          .post(uri, headers: headers, body: body != null ? jsonEncode(body) : null)
          .timeout(const Duration(seconds: 15));
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

  // Multipart POST Request (Admin - for Adding Member with Photo)
  static Future<dynamic> adminMultipartPost(
    String endpoint, {
    required Map<String, String> fields,
    File? file,
    Uint8List? fileBytes,
    String? fileName,
    String fileField = 'photo',
    String? gymCode,
  }) async {
    try {
      final uri = Uri.parse('${ApiConfig.adminBaseUrl}$endpoint');
      final headers = await _getHeaders(gymCode: gymCode, isMultipart: true);

      final request = http.MultipartRequest('POST', uri);
      request.headers.addAll(headers);
      request.fields.addAll(fields);

      if (file != null && !kIsWeb) {
        request.files.add(await http.MultipartFile.fromPath(fileField, file.path));
      } else if (fileBytes != null && fileName != null) {
        request.files.add(http.MultipartFile.fromBytes(fileField, fileBytes, filename: fileName));
      }

      final streamedResponse = await request.send().timeout(const Duration(seconds: 30));
      final response = await http.Response.fromStream(streamedResponse);
      return _handleResponse(response);
    } on SocketException {
      throw ApiException('No internet connection. Please check your network and try again.');
    } on TimeoutException {
      throw ApiException('Upload timed out. Please try again.');
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException('Network upload error: ${e.toString()}');
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
