import 'dart:io';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import 'package:share_plus/share_plus.dart';
import '../../models/admin_transaction_models.dart';

// Semantic PDF Colors
const _emerald = PdfColor.fromInt(0xFF10B981);
const _emerald50 = PdfColor.fromInt(0xFFECFDF5);
const _emerald700 = PdfColor.fromInt(0xFF047857);
const _emerald800 = PdfColor.fromInt(0xFF065F46);
const _emerald900 = PdfColor.fromInt(0xFF064E3B);
const _amber50 = PdfColor.fromInt(0xFFFFFBEB);
const _amber900 = PdfColor.fromInt(0xFF78350F);
const _red50 = PdfColor.fromInt(0xFFFEF2F2);
const _red900 = PdfColor.fromInt(0xFF7F1D1D);

class PdfService {
  /// Generates an official, print-ready A4 Member Registration & Onboarding Document
  static Future<Uint8List> generateRegistrationPdf(MemberRegistrationDocumentData data) async {
    final pdf = pw.Document(
      title: 'Member Registration - ${data.member.fullname}',
      author: data.gym.name,
    );

    pw.ImageProvider? logoImage;
    if (data.gym.logoUrl != null && data.gym.logoUrl!.isNotEmpty) {
      try {
        logoImage = await networkImage(data.gym.logoUrl!);
      } catch (_) {
        logoImage = null;
      }
    }

    final currency = data.gym.currency.isNotEmpty ? data.gym.currency : '₹';
    final isFullyPaid = data.payment.dueAmount <= 0;

    pdf.addPage(
      pw.Page(
        pageFormat: PdfPageFormat.a4,
        margin: const pw.EdgeInsets.all(28),
        build: (pw.Context context) {
          return pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              // Top Accent Strip
              pw.Container(
                height: 4,
                decoration: const pw.BoxDecoration(
                  color: _emerald,
                  borderRadius: pw.BorderRadius.all(pw.Radius.circular(2)),
                ),
              ),
              pw.SizedBox(height: 12),

              // 1. Header (Gym Info & Docket Badge)
              pw.Row(
                crossAxisAlignment: pw.CrossAxisAlignment.start,
                mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                children: [
                  pw.Row(
                    crossAxisAlignment: pw.CrossAxisAlignment.center,
                    children: [
                      if (logoImage != null)
                        pw.Container(
                          width: 48,
                          height: 48,
                          margin: const pw.EdgeInsets.only(right: 12),
                          decoration: pw.BoxDecoration(
                            borderRadius: const pw.BorderRadius.all(pw.Radius.circular(8)),
                            border: pw.Border.all(color: PdfColors.grey300),
                          ),
                          child: pw.ClipRRect(
                            horizontalRadius: 8,
                            verticalRadius: 8,
                            child: pw.Image(logoImage, fit: pw.BoxFit.contain),
                          ),
                        )
                      else
                        pw.Container(
                          width: 48,
                          height: 48,
                          margin: const pw.EdgeInsets.only(right: 12),
                          decoration: const pw.BoxDecoration(
                            color: _emerald,
                            borderRadius: pw.BorderRadius.all(pw.Radius.circular(8)),
                          ),
                          child: pw.Center(
                            child: pw.Text(
                              data.gym.name.isNotEmpty ? data.gym.name[0].toUpperCase() : 'G',
                              style: pw.TextStyle(
                                color: PdfColors.white,
                                fontWeight: pw.FontWeight.bold,
                                fontSize: 22,
                              ),
                            ),
                          ),
                        ),
                      pw.Column(
                        crossAxisAlignment: pw.CrossAxisAlignment.start,
                        children: [
                          pw.Text(
                            data.gym.name.toUpperCase(),
                            style: pw.TextStyle(
                              fontSize: 16,
                              fontWeight: pw.FontWeight.bold,
                              color: PdfColors.grey900,
                            ),
                          ),
                          pw.SizedBox(height: 2),
                          pw.Text(
                            '${data.gym.phone} ${data.gym.email.isNotEmpty ? "• ${data.gym.email}" : ""}',
                            style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700),
                          ),
                          if (data.gym.address.isNotEmpty)
                            pw.Text(
                              data.gym.address,
                              style: const pw.TextStyle(fontSize: 8.5, color: PdfColors.grey600),
                            ),
                        ],
                      ),
                    ],
                  ),
                  pw.Column(
                    crossAxisAlignment: pw.CrossAxisAlignment.end,
                    children: [
                      pw.Container(
                        padding: const pw.EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: pw.BoxDecoration(
                          color: _emerald50,
                          border: pw.Border.all(color: _emerald, width: 0.8),
                          borderRadius: const pw.BorderRadius.all(pw.Radius.circular(12)),
                        ),
                        child: pw.Text(
                          'MEMBER REGISTRATION DOCKET',
                          style: pw.TextStyle(
                            fontSize: 8.5,
                            fontWeight: pw.FontWeight.bold,
                            color: _emerald900,
                          ),
                        ),
                      ),
                      pw.SizedBox(height: 4),
                      pw.Text(
                        data.member.formattedMemberId,
                        style: pw.TextStyle(
                          fontSize: 13,
                          fontWeight: pw.FontWeight.bold,
                          color: PdfColors.grey900,
                        ),
                      ),
                      pw.Text(
                        'Date: ${data.member.joiningDate}',
                        style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey600),
                      ),
                    ],
                  ),
                ],
              ),
              pw.SizedBox(height: 10),
              pw.Divider(thickness: 1, color: PdfColors.grey300),
              pw.SizedBox(height: 8),

              // 2. Member Personal Profile Section
              _buildSectionTitle('1. MEMBER PROFILE & PERSONAL DETAILS'),
              pw.SizedBox(height: 6),
              pw.Container(
                padding: const pw.EdgeInsets.all(10),
                decoration: pw.BoxDecoration(
                  color: PdfColors.grey100,
                  borderRadius: const pw.BorderRadius.all(pw.Radius.circular(6)),
                ),
                child: pw.Column(
                  children: [
                    pw.Row(
                      children: [
                        pw.Expanded(child: _buildDetailRow('Full Name', data.member.fullname, isBold: true)),
                        pw.Expanded(child: _buildDetailRow('Gender', data.member.gender)),
                      ],
                    ),
                    pw.SizedBox(height: 4),
                    pw.Row(
                      children: [
                        pw.Expanded(child: _buildDetailRow('Mobile Number', data.member.phone, isBold: true)),
                        pw.Expanded(child: _buildDetailRow('Email Address', data.member.email.isNotEmpty ? data.member.email : 'N/A')),
                      ],
                    ),
                    pw.SizedBox(height: 4),
                    pw.Row(
                      children: [
                        pw.Expanded(child: _buildDetailRow('Member ID', data.member.formattedMemberId)),
                        pw.Expanded(child: _buildDetailRow('Date of Birth', (data.member.dob != null && data.member.dob!.isNotEmpty) ? data.member.dob! : 'N/A')),
                      ],
                    ),
                    if (data.member.address.isNotEmpty) ...[
                      pw.SizedBox(height: 4),
                      _buildDetailRow('Residential Address', data.member.address),
                    ],
                  ],
                ),
              ),
              pw.SizedBox(height: 10),

              // 3. Membership & Package Subscription
              _buildSectionTitle('2. MEMBERSHIP & PACKAGE DETAILS'),
              pw.SizedBox(height: 6),
              pw.Table(
                border: pw.TableBorder.all(color: PdfColors.grey300, width: 0.5),
                children: [
                  pw.TableRow(
                    decoration: const pw.BoxDecoration(color: PdfColors.grey200),
                    children: [
                      _buildTableHeaderCell('MEMBERSHIP PLAN'),
                      _buildTableHeaderCell('DURATION'),
                      _buildTableHeaderCell('START DATE'),
                      _buildTableHeaderCell('EXPIRY DATE'),
                      _buildTableHeaderCell('STATUS'),
                    ],
                  ),
                  pw.TableRow(
                    children: [
                      _buildTableCell(data.membership.planName, isBold: true),
                      _buildTableCell('${data.membership.planMonths} ${data.membership.planMonths > 1 ? "Months" : "Month"}'),
                      _buildTableCell(data.membership.startDate),
                      _buildTableCell(data.membership.expiryDate),
                      _buildTableCell(data.membership.status.toUpperCase(), color: _emerald800, isBold: true),
                    ],
                  ),
                ],
              ),
              pw.SizedBox(height: 10),

              // 4. Financial Audit & Payment Summary
              _buildSectionTitle('3. FINANCIAL AUDIT & PAYMENT BREAKDOWN'),
              pw.SizedBox(height: 6),
              pw.Row(
                crossAxisAlignment: pw.CrossAxisAlignment.start,
                children: [
                  pw.Expanded(
                    flex: 3,
                    child: pw.Container(
                      padding: const pw.EdgeInsets.all(10),
                      decoration: const pw.BoxDecoration(
                        color: PdfColors.grey100,
                        borderRadius: pw.BorderRadius.all(pw.Radius.circular(6)),
                      ),
                      child: pw.Column(
                        crossAxisAlignment: pw.CrossAxisAlignment.start,
                        children: [
                          _buildDetailRow('Payment Mode', data.payment.paymentMethod),
                          pw.SizedBox(height: 3),
                          _buildDetailRow('Payment Date', data.payment.paymentDate),
                          pw.SizedBox(height: 3),
                          _buildDetailRow('Invoice Ref', data.payment.invoiceNumber),
                          pw.SizedBox(height: 3),
                          _buildDetailRow('Recorded By', 'Admin / Staff'),
                          if (data.payment.transactionRef.isNotEmpty) ...[
                            pw.SizedBox(height: 3),
                            _buildDetailRow('Transaction Ref', data.payment.transactionRef),
                          ],
                        ],
                      ),
                    ),
                  ),
                  pw.SizedBox(width: 10),
                  pw.Expanded(
                    flex: 2,
                    child: pw.Container(
                      padding: const pw.EdgeInsets.all(10),
                      decoration: pw.BoxDecoration(
                        color: PdfColors.grey50,
                        borderRadius: const pw.BorderRadius.all(pw.Radius.circular(6)),
                        border: pw.Border.all(color: PdfColors.grey300),
                      ),
                      child: pw.Column(
                        children: [
                          _buildPriceRow('Total Payable:', '$currency${data.payment.totalAmount.toStringAsFixed(2)}'),
                          if (data.payment.discount > 0)
                            _buildPriceRow('Discount:', '-$currency${data.payment.discount.toStringAsFixed(2)}', isDiscount: true),
                          _buildPriceRow('Amount Paid:', '$currency${data.payment.paidAmount.toStringAsFixed(2)}', isHighlight: true),
                          pw.Divider(thickness: 0.5, color: PdfColors.grey300),
                          _buildPriceRow(
                            'Balance Due:',
                            isFullyPaid ? '$currency 0.00' : '$currency${data.payment.dueAmount.toStringAsFixed(2)}',
                            isBold: true,
                          ),
                          pw.SizedBox(height: 4),
                          pw.Container(
                            width: double.infinity,
                            padding: const pw.EdgeInsets.symmetric(vertical: 3),
                            decoration: pw.BoxDecoration(
                              color: isFullyPaid ? _emerald50 : _red50,
                              borderRadius: const pw.BorderRadius.all(pw.Radius.circular(4)),
                              border: pw.Border.all(color: isFullyPaid ? _emerald : PdfColors.red),
                            ),
                            child: pw.Center(
                              child: pw.Text(
                                isFullyPaid ? 'PAYMENT STATUS: FULLY PAID' : 'PAYMENT STATUS: PARTIAL / DUE',
                                style: pw.TextStyle(
                                  fontSize: 7.5,
                                  fontWeight: pw.FontWeight.bold,
                                  color: isFullyPaid ? _emerald900 : _red900,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              pw.SizedBox(height: 10),

              // 5. Terms & Conditions
              _buildSectionTitle('4. GYM RULES, SAFETY & MEMBERSHIP TERMS'),
              pw.SizedBox(height: 4),
              pw.Container(
                padding: const pw.EdgeInsets.all(8),
                decoration: pw.BoxDecoration(
                  border: pw.Border.all(color: PdfColors.grey300, width: 0.5),
                  borderRadius: const pw.BorderRadius.all(pw.Radius.circular(6)),
                ),
                child: pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.start,
                  children: [
                    ...(data.gym.terms.isNotEmpty
                            ? data.gym.terms.split('\n').where((s) => s.trim().isNotEmpty).toList()
                            : [
                                'Gym membership is non-transferable and subscription fees are non-refundable once activated.',
                                'Members are required to follow safety guidelines, use towels, and return equipment after usage.',
                                'The gym management is not liable for loss of personal belongings inside the facility.',
                              ])
                        .map(
                      (term) => pw.Padding(
                        padding: const pw.EdgeInsets.only(bottom: 2),
                        child: pw.Row(
                          crossAxisAlignment: pw.CrossAxisAlignment.start,
                          children: [
                            pw.Text('• ', style: const pw.TextStyle(fontSize: 7.5, color: PdfColors.grey700)),
                            pw.Expanded(
                              child: pw.Text(
                                term,
                                style: const pw.TextStyle(fontSize: 7.5, color: PdfColors.grey700),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              pw.Spacer(),

              // Signatures
              pw.Row(
                mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                children: [
                  pw.Column(
                    crossAxisAlignment: pw.CrossAxisAlignment.center,
                    children: [
                      pw.Container(width: 140, height: 1, color: PdfColors.grey600),
                      pw.SizedBox(height: 4),
                      pw.Text('Member Signature', style: const pw.TextStyle(fontSize: 8.5, color: PdfColors.grey800)),
                    ],
                  ),
                  pw.Column(
                    crossAxisAlignment: pw.CrossAxisAlignment.center,
                    children: [
                      pw.Container(width: 140, height: 1, color: PdfColors.grey600),
                      pw.SizedBox(height: 4),
                      pw.Text('Authorized Gym Manager / Signatory', style: const pw.TextStyle(fontSize: 8.5, color: PdfColors.grey800)),
                    ],
                  ),
                ],
              ),
              pw.SizedBox(height: 8),
              pw.Center(
                child: pw.Text(
                  'Computer Generated Official Onboarding Record • Issued on ${data.generatedAt} • Fitisify OS',
                  style: const pw.TextStyle(fontSize: 7, color: PdfColors.grey500),
                ),
              ),
            ],
          );
        },
      ),
    );

    return pdf.save();
  }

  /// Generates an official Payment Receipt PDF
  static Future<Uint8List> generateReceiptPdf(
    AdminTransactionItem txn, {
    String gymName = 'Our Gym',
    String gymPhone = '',
    String gymAddress = '',
    String? gymLogoUrl,
    String currency = '₹',
  }) async {
    final pdf = pw.Document(
      title: 'Payment Receipt - ${txn.invoiceNumber}',
      author: gymName,
    );

    pw.ImageProvider? logoImage;
    if (gymLogoUrl != null && gymLogoUrl.isNotEmpty) {
      try {
        logoImage = await networkImage(gymLogoUrl);
      } catch (_) {
        logoImage = null;
      }
    }

    final isPaid = txn.dueAmount <= 0;

    pdf.addPage(
      pw.Page(
        pageFormat: PdfPageFormat.a4,
        margin: const pw.EdgeInsets.all(32),
        build: (pw.Context context) {
          return pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              pw.Container(
                height: 5,
                decoration: const pw.BoxDecoration(
                  color: PdfColors.blue600,
                  borderRadius: pw.BorderRadius.all(pw.Radius.circular(2)),
                ),
              ),
              pw.SizedBox(height: 14),

              // Header
              pw.Row(
                mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                crossAxisAlignment: pw.CrossAxisAlignment.start,
                children: [
                  pw.Row(
                    children: [
                      if (logoImage != null)
                        pw.Container(
                          width: 44,
                          height: 44,
                          margin: const pw.EdgeInsets.only(right: 10),
                          child: pw.Image(logoImage, fit: pw.BoxFit.contain),
                        ),
                      pw.Column(
                        crossAxisAlignment: pw.CrossAxisAlignment.start,
                        children: [
                          pw.Text(
                            gymName.toUpperCase(),
                            style: pw.TextStyle(fontSize: 16, fontWeight: pw.FontWeight.bold, color: PdfColors.grey900),
                          ),
                          if (gymPhone.isNotEmpty)
                            pw.Text(gymPhone, style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700)),
                          if (gymAddress.isNotEmpty)
                            pw.Text(gymAddress, style: const pw.TextStyle(fontSize: 8.5, color: PdfColors.grey600)),
                        ],
                      ),
                    ],
                  ),
                  pw.Column(
                    crossAxisAlignment: pw.CrossAxisAlignment.end,
                    children: [
                      pw.Container(
                        padding: const pw.EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: pw.BoxDecoration(
                          color: isPaid ? _emerald50 : _amber50,
                          border: pw.Border.all(color: isPaid ? _emerald : PdfColors.amber, width: 0.8),
                          borderRadius: const pw.BorderRadius.all(pw.Radius.circular(10)),
                        ),
                        child: pw.Text(
                          'PAYMENT RECEIPT',
                          style: pw.TextStyle(
                            fontSize: 9,
                            fontWeight: pw.FontWeight.bold,
                            color: isPaid ? _emerald900 : _amber900,
                          ),
                        ),
                      ),
                      pw.SizedBox(height: 4),
                      pw.Text(txn.invoiceNumber, style: pw.TextStyle(fontSize: 12, fontWeight: pw.FontWeight.bold)),
                      pw.Text('Date: ${txn.paymentDate}', style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey600)),
                    ],
                  ),
                ],
              ),
              pw.SizedBox(height: 12),
              pw.Divider(thickness: 1, color: PdfColors.grey300),
              pw.SizedBox(height: 10),

              // Billed To & Payment Details
              pw.Row(
                children: [
                  pw.Expanded(
                    child: pw.Container(
                      padding: const pw.EdgeInsets.all(10),
                      decoration: pw.BoxDecoration(
                        color: PdfColors.grey100,
                        borderRadius: const pw.BorderRadius.all(pw.Radius.circular(6)),
                      ),
                      child: pw.Column(
                        crossAxisAlignment: pw.CrossAxisAlignment.start,
                        children: [
                          pw.Text('BILLED TO (MEMBER):', style: pw.TextStyle(fontSize: 8, fontWeight: pw.FontWeight.bold, color: PdfColors.grey600)),
                          pw.SizedBox(height: 4),
                          pw.Text(txn.memberName, style: pw.TextStyle(fontSize: 12, fontWeight: pw.FontWeight.bold)),
                          pw.Text('Member ID: #MEM-${txn.memberId.toString().padLeft(4, "0")}', style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700)),
                          pw.Text('Phone: ${txn.memberPhone}', style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700)),
                        ],
                      ),
                    ),
                  ),
                  pw.SizedBox(width: 12),
                  pw.Expanded(
                    child: pw.Container(
                      padding: const pw.EdgeInsets.all(10),
                      decoration: pw.BoxDecoration(
                        color: PdfColors.grey100,
                        borderRadius: const pw.BorderRadius.all(pw.Radius.circular(6)),
                      ),
                      child: pw.Column(
                        crossAxisAlignment: pw.CrossAxisAlignment.start,
                        children: [
                          pw.Text('TRANSACTION INFO:', style: pw.TextStyle(fontSize: 8, fontWeight: pw.FontWeight.bold, color: PdfColors.grey600)),
                          pw.SizedBox(height: 4),
                          pw.Text('Mode: ${txn.paymentMethod}', style: const pw.TextStyle(fontSize: 9.5, color: PdfColors.grey900)),
                          pw.Text('Txn Ref: ${txn.transactionRef}', style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700)),
                          pw.Text('Collected By: ${txn.collectedBy}', style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700)),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              pw.SizedBox(height: 16),

              // Itemized Table
              pw.Table(
                border: pw.TableBorder.all(color: PdfColors.grey300, width: 0.5),
                children: [
                  pw.TableRow(
                    decoration: const pw.BoxDecoration(color: PdfColors.grey200),
                    children: [
                      _buildTableHeaderCell('DESCRIPTION / SERVICE'),
                      _buildTableHeaderCell('PLAN DURATION'),
                      _buildTableHeaderCell('AMOUNT'),
                    ],
                  ),
                  pw.TableRow(
                    children: [
                      _buildTableCell(txn.serviceName, isBold: true),
                      _buildTableCell('${txn.planMonths} ${txn.planMonths > 1 ? "Months" : "Month"}'),
                      _buildTableCell('$currency${txn.amount.toStringAsFixed(2)}', isBold: true),
                    ],
                  ),
                ],
              ),
              pw.SizedBox(height: 14),

              // Summary
              pw.Row(
                mainAxisAlignment: pw.MainAxisAlignment.end,
                children: [
                  pw.Container(
                    width: 240,
                    padding: const pw.EdgeInsets.all(10),
                    decoration: pw.BoxDecoration(
                      color: PdfColors.grey50,
                      borderRadius: const pw.BorderRadius.all(pw.Radius.circular(6)),
                      border: pw.Border.all(color: PdfColors.grey300),
                    ),
                    child: pw.Column(
                      children: [
                        _buildPriceRow('Total Invoiced:', '$currency${txn.amount.toStringAsFixed(2)}'),
                        if (txn.discount > 0)
                          _buildPriceRow('Discount:', '-$currency${txn.discount.toStringAsFixed(2)}', isDiscount: true),
                        _buildPriceRow('Amount Paid:', '$currency${txn.paidAmount.toStringAsFixed(2)}', isHighlight: true),
                        pw.Divider(thickness: 0.5, color: PdfColors.grey300),
                        _buildPriceRow(
                          'Balance Due:',
                          isPaid ? '$currency 0.00 (CLEAR)' : '$currency${txn.dueAmount.toStringAsFixed(2)}',
                          isBold: true,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              pw.Spacer(),

              // Signatures
              pw.Row(
                mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                children: [
                  pw.Column(
                    crossAxisAlignment: pw.CrossAxisAlignment.center,
                    children: [
                      pw.Container(width: 130, height: 1, color: PdfColors.grey600),
                      pw.SizedBox(height: 4),
                      pw.Text('Member Signature', style: const pw.TextStyle(fontSize: 8.5, color: PdfColors.grey800)),
                    ],
                  ),
                  pw.Column(
                    crossAxisAlignment: pw.CrossAxisAlignment.center,
                    children: [
                      pw.Container(width: 130, height: 1, color: PdfColors.grey600),
                      pw.SizedBox(height: 4),
                      pw.Text('Authorized Signatory', style: const pw.TextStyle(fontSize: 8.5, color: PdfColors.grey800)),
                    ],
                  ),
                ],
              ),
              pw.SizedBox(height: 10),
              pw.Center(
                child: pw.Text(
                  'Thank you for your business! • Fitisify SaaS Official Receipt',
                  style: const pw.TextStyle(fontSize: 7.5, color: PdfColors.grey500),
                ),
              ),
            ],
          );
        },
      ),
    );

    return pdf.save();
  }

  // Preview Action
  static Future<void> previewPdf(BuildContext context, Uint8List pdfBytes, String documentTitle) async {
    await Printing.layoutPdf(
      name: documentTitle,
      onLayout: (PdfPageFormat format) async => pdfBytes,
    );
  }

  // Download Action
  static Future<String?> downloadPdf(Uint8List pdfBytes, String filename) async {
    try {
      final outputDir = await getApplicationDocumentsDirectory();
      final file = File('${outputDir.path}/$filename');
      await file.writeAsBytes(pdfBytes, flush: true);
      await OpenFilex.open(file.path);
      return file.path;
    } catch (e) {
      debugPrint('Error saving PDF: $e');
      return null;
    }
  }

  // Share Action (Native file attachment for WhatsApp, Telegram, Gmail, etc.)
  static Future<void> sharePdf(Uint8List pdfBytes, String filename, {String? subject, String? text}) async {
    try {
      final tempDir = await getTemporaryDirectory();
      final file = File('${tempDir.path}/$filename');
      await file.writeAsBytes(pdfBytes, flush: true);

      await Share.shareXFiles(
        [XFile(file.path, mimeType: 'application/pdf', name: filename)],
        subject: subject ?? filename,
        text: text,
      );
    } catch (e) {
      debugPrint('Error sharing PDF: $e');
    }
  }

  // Registration PDF convenience methods
  static Future<void> previewRegistrationPdf(BuildContext context, MemberRegistrationDocumentData data) async {
    final bytes = await generateRegistrationPdf(data);
    if (context.mounted) {
      await previewPdf(context, bytes, 'Member_Registration_${data.member.memberId}.pdf');
    }
  }

  static Future<String?> downloadRegistrationPdf(BuildContext context, MemberRegistrationDocumentData data) async {
    final bytes = await generateRegistrationPdf(data);
    final filename = 'Member_Registration_${data.member.memberId}.pdf';
    final path = await downloadPdf(bytes, filename);
    if (context.mounted && path != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Registration PDF saved: $filename')),
      );
    }
    return path;
  }

  static Future<void> shareRegistrationPdf(BuildContext context, MemberRegistrationDocumentData data) async {
    final bytes = await generateRegistrationPdf(data);
    final filename = 'Member_Registration_${data.member.memberId}.pdf';
    await sharePdf(bytes, filename, subject: 'Gym Registration - ${data.member.fullname}', text: 'Official Membership Registration Document for ${data.member.fullname} (${data.gym.name})');
  }

  // Payment Receipt PDF convenience methods
  static Future<void> previewReceiptPdf(BuildContext context, AdminTransactionItem txn) async {
    final bytes = await generateReceiptPdf(txn);
    if (context.mounted) {
      await previewPdf(context, bytes, 'Payment_Receipt_${txn.invoiceNumber}.pdf');
    }
  }

  static Future<String?> downloadReceiptPdf(BuildContext context, AdminTransactionItem txn) async {
    final bytes = await generateReceiptPdf(txn);
    final filename = 'Payment_Receipt_${txn.invoiceNumber}.pdf';
    final path = await downloadPdf(bytes, filename);
    if (context.mounted && path != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Payment Receipt saved: $filename')),
      );
    }
    return path;
  }

  static Future<void> shareReceiptPdf(BuildContext context, AdminTransactionItem txn) async {
    final bytes = await generateReceiptPdf(txn);
    final filename = 'Payment_Receipt_${txn.invoiceNumber}.pdf';
    await sharePdf(bytes, filename, subject: 'Payment Receipt ${txn.invoiceNumber}', text: 'Payment Receipt ${txn.invoiceNumber} for ${txn.memberName}');
  }

  static Future<void> printReceiptPdf(BuildContext context, AdminTransactionItem txn) async {
    final bytes = await generateReceiptPdf(txn);
    await Printing.layoutPdf(
      name: 'Payment_Receipt_${txn.invoiceNumber}',
      onLayout: (PdfPageFormat format) async => bytes,
    );
  }

  // --- Internal Helper Widgets for PDF ---
  static pw.Widget _buildSectionTitle(String title) {
    return pw.Text(
      title,
      style: pw.TextStyle(
        fontSize: 8.5,
        fontWeight: pw.FontWeight.bold,
        color: PdfColors.grey700,
        letterSpacing: 0.5,
      ),
    );
  }

  static pw.Widget _buildDetailRow(String label, String value, {bool isBold = false}) {
    return pw.Row(
      crossAxisAlignment: pw.CrossAxisAlignment.start,
      children: [
        pw.SizedBox(
          width: 90,
          child: pw.Text(
            '$label:',
            style: const pw.TextStyle(fontSize: 8.5, color: PdfColors.grey600),
          ),
        ),
        pw.Expanded(
          child: pw.Text(
            value,
            style: pw.TextStyle(
              fontSize: 9,
              fontWeight: isBold ? pw.FontWeight.bold : pw.FontWeight.normal,
              color: PdfColors.grey900,
            ),
          ),
        ),
      ],
    );
  }

  static pw.Widget _buildTableHeaderCell(String text) {
    return pw.Padding(
      padding: const pw.EdgeInsets.all(6),
      child: pw.Text(
        text,
        style: pw.TextStyle(fontSize: 8, fontWeight: pw.FontWeight.bold, color: PdfColors.grey800),
      ),
    );
  }

  static pw.Widget _buildTableCell(String text, {bool isBold = false, PdfColor? color}) {
    return pw.Padding(
      padding: const pw.EdgeInsets.all(6),
      child: pw.Text(
        text,
        style: pw.TextStyle(
          fontSize: 8.5,
          fontWeight: isBold ? pw.FontWeight.bold : pw.FontWeight.normal,
          color: color ?? PdfColors.grey900,
        ),
      ),
    );
  }

  static pw.Widget _buildPriceRow(String label, String value, {bool isHighlight = false, bool isDiscount = false, bool isBold = false}) {
    return pw.Padding(
      padding: const pw.EdgeInsets.symmetric(vertical: 1.5),
      child: pw.Row(
        mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
        children: [
          pw.Text(
            label,
            style: pw.TextStyle(
              fontSize: 8.5,
              fontWeight: isBold ? pw.FontWeight.bold : pw.FontWeight.normal,
              color: isDiscount ? _emerald700 : PdfColors.grey700,
            ),
          ),
          pw.Text(
            value,
            style: pw.TextStyle(
              fontSize: 9,
              fontWeight: (isHighlight || isBold) ? pw.FontWeight.bold : pw.FontWeight.normal,
              color: isHighlight
                  ? _emerald800
                  : isDiscount
                      ? _emerald700
                      : PdfColors.grey900,
            ),
          ),
        ],
      ),
    );
  }
}
