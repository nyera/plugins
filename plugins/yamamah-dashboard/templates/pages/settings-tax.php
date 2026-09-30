<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tax_mode = function_exists( 'yamamah_get_booking_tax_mode' )
	? yamamah_get_booking_tax_mode()
	: (string) get_option( 'yamamah_tax_mode', 'exclusive' );
$tax_mode = in_array( $tax_mode, array( 'exclusive', 'inclusive' ), true ) ? $tax_mode : 'exclusive';

ob_start();
?>
<div class="page active" id="page-settings-tax">
	<div class="page-header">
		<h1>إعدادات الضرائب</h1>
		<p>ضريبة القيمة المضافة والنسب الضريبية — لجميع أنواع المتاجر والثيمات</p>
	</div>

	<form id="yamamahSettingsForm" method="post">
		<?php wp_nonce_field( 'yamamah_settings', 'yamamah_settings_nonce' ); ?>
		<div class="card space-y-4">
			<div class="flex justify-between items-center mb-4">
				<span class="text-sm">تفعيل الضريبة</span>
				<label class="toggle-label">
					<input type="hidden" name="yamamah_tax_enabled" value="no">
					<input type="checkbox" name="yamamah_tax_enabled" value="yes"
						<?php checked( $settings['tax_enabled'], 'yes' ); ?>>
					<div class="toggle <?php echo $settings['tax_enabled'] === 'yes' ? 'on' : ''; ?>"></div>
				</label>
			</div>
			<div class="form-row">
				<div class="form-group">
					<label>نسبة الضريبة %</label>
					<input type="number" class="form-control" name="yamamah_tax_rate" min="0" step="0.01"
						   value="<?php echo esc_attr( $settings['tax_rate'] ); ?>">
				</div>
				<div class="form-group">
					<label>اسم الضريبة</label>
					<input type="text" class="form-control" value="ضريبة القيمة المضافة" readonly>
				</div>
			</div>
		</div>

		<div class="card space-y-4 mt-4">
			<div class="card-section-title" style="font-size:1.05rem;font-weight:700;margin-bottom:0.5rem;">طريقة عرض الضريبة على الإجمالي</div>
			<div class="form-group">
				<div style="display:flex;flex-direction:column;gap:0.75rem;">
					<label style="display:flex;align-items:flex-start;gap:0.6rem;cursor:pointer;padding:0.75rem 1rem;border:1px solid var(--border,#e5e7eb);border-radius:8px;<?php echo 'exclusive' === $tax_mode ? 'border-color:#16a34a;background:rgba(22,163,74,0.06);' : ''; ?>">
						<input type="radio" name="yamamah_tax_mode" value="exclusive" <?php checked( $tax_mode, 'exclusive' ); ?> style="margin-top:3px;">
						<span>
							<strong style="display:block;">غير متضمنة في الإجمالي</strong>
							<small class="text-muted">تُضاف الضريبة فوق السعر (المجموع الفرعي + الضريبة = الإجمالي).</small>
						</span>
					</label>
					<label style="display:flex;align-items:flex-start;gap:0.6rem;cursor:pointer;padding:0.75rem 1rem;border:1px solid var(--border,#e5e7eb);border-radius:8px;<?php echo 'inclusive' === $tax_mode ? 'border-color:#16a34a;background:rgba(22,163,74,0.06);' : ''; ?>">
						<input type="radio" name="yamamah_tax_mode" value="inclusive" <?php checked( $tax_mode, 'inclusive' ); ?> style="margin-top:3px;">
						<span>
							<strong style="display:block;">متضمنة في الإجمالي</strong>
							<small class="text-muted">السعر المعروض شامل الضريبة، مع إمكانية إظهار بند «منها ضريبة».</small>
						</span>
					</label>
				</div>
				<small class="text-muted" style="display:block;margin-top:0.75rem;">يُطبَّق على السلة والدفع.</small>
			</div>
		</div>

		<div class="mt-4">
			<button type="submit" class="btn btn-green">حفظ الإعدادات</button>
			<div id="settingsSaveResult" class="alert alert-success mt-3" style="display:none;">تم الحفظ بنجاح</div>
		</div>
	</form>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
