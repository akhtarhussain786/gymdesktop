import 'package:flutter/material.dart';
import '../core/config/api_config.dart';
import '../core/network/api_service.dart';
import '../widgets/branded_button.dart';

class ForgotPasswordDialog extends StatefulWidget {
  final String gymCode;

  const ForgotPasswordDialog({super.key, required this.gymCode});

  @override
  State<ForgotPasswordDialog> createState() => _ForgotPasswordDialogState();
}

class _ForgotPasswordDialogState extends State<ForgotPasswordDialog> {
  final _emailController = TextEditingController();
  bool _isLoading = false;
  String? _message;
  bool _isSuccess = false;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final email = _emailController.text.trim();
    if (email.isEmpty) return;

    setState(() {
      _isLoading = true;
      _message = null;
    });

    try {
      await ApiService.post(ApiConfig.forgotPassword, body: {
        'gym_code': widget.gymCode,
        'email': email,
      });

      setState(() {
        _isLoading = false;
        _isSuccess = true;
        _message = 'Password reset instructions have been logged. Please check your inbox or visit gym reception.';
      });
    } on ApiException catch (e) {
      setState(() {
        _isLoading = false;
        _isSuccess = false;
        _message = e.message;
      });
    } catch (_) {
      setState(() {
        _isLoading = false;
        _isSuccess = false;
        _message = 'Failed to submit reset request. Please contact gym reception.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return AlertDialog(
      title: const Text('Reset Password'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Enter your registered email address or member username for Gym Code (${widget.gymCode}):',
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: 16),
            if (!_isSuccess) ...[
              TextField(
                controller: _emailController,
                decoration: const InputDecoration(
                  labelText: 'Email or Username',
                  prefixIcon: Icon(Icons.email_outlined),
                ),
                keyboardType: TextInputType.emailAddress,
              ),
            ],
            if (_message != null) ...[
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: _isSuccess
                      ? Colors.green.withValues(alpha: 0.15)
                      : Colors.red.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  _message!,
                  style: TextStyle(
                    color: _isSuccess ? Colors.green : Colors.red,
                    fontSize: 13,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(_isSuccess ? 'Done' : 'Cancel'),
        ),
        if (!_isSuccess)
          SizedBox(
            width: 120,
            child: BrandedButton(
              text: 'Submit',
              height: 38,
              isLoading: _isLoading,
              onPressed: _submit,
            ),
          ),
      ],
    );
  }
}
