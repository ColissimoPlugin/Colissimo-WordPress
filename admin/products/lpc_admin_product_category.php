<?php

class LpcAdminProductCategory extends LpcComponent {
	/** @var LpcAccountApi */
	private $accountApi;

	public function __construct(?LpcAccountApi $accountApi = null) {
		$this->accountApi = LpcRegister::get('accountApi', $accountApi);
	}

	public function getDependencies(): array {
		return ['accountApi'];
	}

	public function init() {
		add_action('product_cat_add_form_fields', [$this, 'addCategoryHazmatListing'], 11);
		add_action('product_cat_edit_form_fields', [$this, 'addCategoryHazmatEdition'], 11);
		add_action('created_term', [$this, 'saveCategoryHazmat'], 11, 3);
		add_action('edit_term', [$this, 'saveCategoryHazmat'], 11, 3);
	}

	public function addCategoryHazmatListing() {
		if (!$this->accountApi->isHazmatOptionActive()) {
			return;
		}
		?>
		<div class="form-field term-lpc-hazmat-wrap">
			<label for="<?php echo esc_attr(LpcLabelGenerationPayload::HAZMAT_ATTRIBUTE); ?>">
				<?php esc_html_e('Default Colissimo hazmat category', 'wc_colissimo'); ?>
			</label>
			<select name="<?php echo esc_attr(LpcLabelGenerationPayload::HAZMAT_ATTRIBUTE); ?>" id="<?php echo esc_attr(LpcLabelGenerationPayload::HAZMAT_ATTRIBUTE); ?>">
				<option value=""><?php esc_html_e('None', 'wc_colissimo'); ?></option>
				<?php foreach (LpcLabelGenerationPayload::HAZMAT_CATEGORIES as $key => $category) { ?>
					<option value="<?php echo esc_attr($key); ?>">
						<?php esc_html_e($category['label'], 'wc_colissimo'); ?>
					</option>
				<?php } ?>
			</select>
		</div>
		<?php
	}

	public function addCategoryHazmatEdition($term) {
		if (!$this->accountApi->isHazmatOptionActive()) {
			return;
		}

		$selectedCategory = get_term_meta($term->term_id, LpcLabelGenerationPayload::HAZMAT_ATTRIBUTE, true);
		?>
		<tr class="form-field term-lpc-hazmat-wrap">
			<th scope="row">
				<label for="<?php echo esc_attr(LpcLabelGenerationPayload::HAZMAT_ATTRIBUTE); ?>">
					<?php esc_html_e('Default Colissimo hazmat category', 'wc_colissimo'); ?>
				</label>
			</th>
			<td>
				<select name="<?php echo esc_attr(LpcLabelGenerationPayload::HAZMAT_ATTRIBUTE); ?>" id="<?php echo esc_attr(LpcLabelGenerationPayload::HAZMAT_ATTRIBUTE); ?>">
					<option value=""><?php esc_html_e('None', 'wc_colissimo'); ?></option>
					<?php foreach (LpcLabelGenerationPayload::HAZMAT_CATEGORIES as $key => $category) { ?>
						<option value="<?php echo esc_attr($key); ?>" <?php selected($selectedCategory === $key); ?>>
							<?php esc_html_e($category['label'], 'wc_colissimo'); ?>
						</option>
					<?php } ?>
				</select>
			</td>
		</tr>
		<?php
	}

	public function saveCategoryHazmat($termId, $termTaxonomyId = '', $taxonomySlug = '') {
		$category = LpcHelper::getVar(LpcLabelGenerationPayload::HAZMAT_ATTRIBUTE);
		if ('product_cat' !== $taxonomySlug || empty($category)) {
			return;
		}

		if ($this->accountApi->isHazmatOptionActive()) {
			update_term_meta($termId, LpcLabelGenerationPayload::HAZMAT_ATTRIBUTE, $category);
		}
	}
}
