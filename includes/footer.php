    </div> <!-- End .app-content -->
</main> <!-- End .app-main -->
</div> <!-- End .app-wrapper -->

<!-- Global Toast Notification Container -->
<div id="toast-container"></div>

<!-- Core App Scripts -->
<script src="<?php echo base_url('/assets/js/app.js'); ?>"></script>

<?php
// Auto-render any pending flash notification
$flash = get_flash();
if ($flash):
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    <?php $jsFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP; ?>
    App.toast(<?php echo json_encode((string)$flash['type'], $jsFlags); ?>, <?php echo json_encode(e($flash['message']), $jsFlags); /* App.toast uses innerHTML: pre-escape */ ?>);
});
</script>
<?php endif; ?>

</body>
</html>
