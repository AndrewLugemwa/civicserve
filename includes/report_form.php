<?php
/**
 * The report form, shared by the resident portal (index.php) and staff intake (intake.php).
 * Expects: $formState = ['input' => [...], 'errors' => [...]], $staffMode (bool).
 */
$in  = $formState['input'] ?? [];
$err = $formState['errors'] ?? [];
$val = fn(string $k) => e($in[$k] ?? '');
$fieldClass = fn(string $k) => 'field' . (isset($err[$k]) ? ' has-error' : '');
$errorText = fn(string $k) => '<p class="field-error" id="err-' . e($k) . '">' . e($err[$k] ?? '') . '</p>';
$describedBy = fn(string $k, string $hint = '') => ' aria-describedby="' . trim(($hint ? $hint . ' ' : '') . 'err-' . $k) . '"' . (isset($err[$k]) ? ' aria-invalid="true"' : '');
?>
<?php if ($err): ?>
    <div class="alert alert-error" role="alert" tabindex="-1" id="form-errors">
        <strong>Please check <?= count($err) === 1 ? 'one detail' : count($err) . ' details' ?> below.</strong>
        <ul><?php foreach ($err as $field => $message): ?><li><a href="#f-<?= e($field) ?>"><?= e($message) ?></a></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form class="report-form" method="post" action="process_report.php" enctype="multipart/form-data" novalidate data-report-form>
    <?= csrf_field() ?>
    <input type="hidden" name="form_once" value="<?= e(form_once_token('report')) ?>">
    <input type="hidden" name="max_file_size" value="<?= (int)cfg('uploads.max_bytes') ?>">

    <?php if ($staffMode): ?>
    <fieldset class="form-section">
        <legend><span class="legend-num">0</span>How was this complaint received?</legend>
        <div class="form-grid">
            <div class="<?= $fieldClass('channel') ?>">
                <label for="f-channel">Received via</label>
                <select id="f-channel" name="channel"<?= $describedBy('channel') ?>>
                    <?php foreach (cfg('channels') as $key => $label): if ($key === 'online') continue; ?>
                        <option value="<?= e($key) ?>"<?= ($in['channel'] ?? 'walk_in') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $errorText('channel') ?>
            </div>
        </div>
    </fieldset>
    <?php endif; ?>

    <fieldset class="form-section">
        <legend><span class="legend-num">1</span>What is the issue?</legend>
        <div class="<?= $fieldClass('category') ?>" id="f-category" tabindex="-1">
            <span class="label" id="category-label">Category</span>
            <div class="category-grid" role="radiogroup" aria-labelledby="category-label"<?= $describedBy('category') ?>>
                <?php foreach (cfg('categories') as $key => $cat): ?>
                    <label class="category-option">
                        <input type="radio" name="category" value="<?= e($key) ?>" required<?= ($in['category'] ?? '') === $key ? ' checked' : '' ?>>
                        <span><strong><?= e($cat['label']) ?></strong><small><?= e($cat['hint']) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?= $errorText('category') ?>
        </div>

        <div class="<?= $fieldClass('description') ?>" style="margin-top:16px">
            <label for="f-description">Describe the problem</label>
            <p class="hint" id="hint-description">What is happening, since when, and who is affected. At least 15 characters.</p>
            <textarea id="f-description" name="description" maxlength="2000" minlength="15" required data-count<?= $describedBy('description', 'hint-description') ?>><?= $val('description') ?></textarea>
            <div class="char-count" data-count-for="f-description" aria-live="polite"></div>
            <?= $errorText('description') ?>
        </div>
    </fieldset>

    <fieldset class="form-section">
        <legend><span class="legend-num">2</span>Where is it?</legend>
        <div class="form-grid">
            <div class="<?= $fieldClass('division') ?>">
                <label for="f-division"><?= e(area_label()) ?></label>
                <select id="f-division" name="division" required<?= $describedBy('division') ?>>
                    <option value="">Choose <?= e(preg_match('/^[aeiou]/i', area_label()) ? 'an' : 'a') ?> <?= e(strtolower(area_label())) ?></option>
                    <?php foreach (cfg('divisions') as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= ($in['division'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $errorText('division') ?>
            </div>
            <div class="<?= $fieldClass('location') ?>">
                <label for="f-location">Street, village or landmark</label>
                <input id="f-location" name="location" type="text" maxlength="255" required autocomplete="street-address" placeholder="e.g. Kigo Road, near Kigo Primary School" value="<?= $val('location') ?>"<?= $describedBy('location') ?>>
                <?= $errorText('location') ?>
            </div>
            <div class="field field-full<?= isset($err['coordinates']) ? ' has-error' : '' ?>" id="f-coordinates" tabindex="-1">
                <span class="label">Pin the exact spot <span class="optional">(optional, helps teams find it faster)</span></span>
                <div class="map-frame">
                    <div class="map-canvas" id="incident-map" role="application" aria-label="Map: click or tap to place a pin on the issue location"
                         data-lat="<?= $val('latitude') ?>" data-lng="<?= $val('longitude') ?>"
                         data-default-lat="<?= e((string)cfg('map.lat')) ?>" data-default-lng="<?= e((string)cfg('map.lng')) ?>" data-default-zoom="<?= (int)cfg('map.zoom', 13) ?>"></div>
                    <div class="map-toolbar">
                        <span id="map-status" aria-live="polite">Tap the map to place a pin.</span>
                        <span class="button-row">
                            <button type="button" class="button button-secondary button-small" id="use-location">Use my location</button>
                            <button type="button" class="button button-secondary button-small" id="clear-pin" hidden>Remove pin</button>
                        </span>
                    </div>
                </div>
                <input type="hidden" name="latitude" id="latitude" value="<?= $val('latitude') ?>">
                <input type="hidden" name="longitude" id="longitude" value="<?= $val('longitude') ?>">
                <details class="manual-toggle" id="manual-details"<?= isset($err['coordinates']) ? ' open' : '' ?>>
                    <summary>Enter coordinates instead</summary>
                    <div class="manual-coords">
                        <div class="field"><label for="manual-lat">Latitude</label><input id="manual-lat" type="text" inputmode="decimal" placeholder="<?= e((string)cfg('map.lat')) ?>"></div>
                        <div class="field"><label for="manual-lng">Longitude</label><input id="manual-lng" type="text" inputmode="decimal" placeholder="<?= e((string)cfg('map.lng')) ?>"></div>
                        <button type="button" class="button button-secondary" id="apply-coords">Use these</button>
                    </div>
                </details>
                <?= $errorText('coordinates') ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="form-section">
        <legend><span class="legend-num">3</span>Add a photo <span class="optional">(optional)</span></legend>
        <div class="<?= $fieldClass('photo') ?>" id="f-photo-wrap">
            <div class="photo-drop">
                <img class="photo-preview" id="photo-preview" alt="Selected photo preview" hidden>
                <div>
                    <label for="f-photo">Photo of the issue</label>
                    <p class="hint" id="hint-photo">JPG, PNG or WebP, up to <?= (int)(cfg('uploads.max_bytes') / 1048576) ?> MB. Avoid photos that show people's faces or number plates.</p>
                    <input id="f-photo" name="issue_photo" type="file" accept="image/jpeg,image/png,image/webp"<?= $describedBy('photo', 'hint-photo') ?>>
                </div>
            </div>
            <?= $errorText('photo') ?>
        </div>
    </fieldset>

    <fieldset class="form-section">
        <legend><span class="legend-num">4</span><?= $staffMode ? "Complainant's details" : 'Your contact details' ?></legend>
        <div class="form-grid">
            <div class="<?= $fieldClass('name') ?>">
                <label for="f-name">Full name</label>
                <input id="f-name" name="citizen_name" type="text" maxlength="120" required autocomplete="name" value="<?= $val('citizen_name') ?>"<?= $describedBy('name') ?>>
                <?= $errorText('name') ?>
            </div>
            <div class="<?= $fieldClass('phone') ?>">
                <label for="f-phone">Phone number</label>
                <input id="f-phone" name="phone" type="tel" maxlength="20" required autocomplete="tel" inputmode="tel" placeholder="07XX XXX XXX" value="<?= $val('phone') ?>"<?= $describedBy('phone', 'hint-phone') ?>>
                <p class="hint" id="hint-phone">Ugandan number. Staff may call to clarify the location.</p>
                <?= $errorText('phone') ?>
            </div>
            <div class="<?= $fieldClass('email') ?> field-full">
                <label for="f-email">Email <span class="optional">(optional)</span></label>
                <input id="f-email" name="citizen_email" type="email" maxlength="190" autocomplete="email" value="<?= $val('citizen_email') ?>"<?= $describedBy('email') ?>>
                <?= $errorText('email') ?>
            </div>
            <div class="<?= $fieldClass('consent') ?> field-full" id="f-consent" tabindex="-1">
                <label class="checkbox">
                    <input type="checkbox" name="consent" value="1" required<?= !empty($in['consent']) ? ' checked' : '' ?>>
                    <span><?= $staffMode
                        ? 'The complainant agrees that the Council may use these details to handle and follow up this report.'
                        : 'I confirm these details are accurate, and I agree that the Council may use them to handle and follow up this report.' ?></span>
                </label>
                <?= $errorText('consent') ?>
            </div>
        </div>
    </fieldset>

    <div class="form-footer">
        <p>Your name and phone number are seen only by authorised Council staff. The public tracking page shows the report's progress only.</p>
        <button type="submit" class="button button-primary" data-submit-label="Submitting…">Submit report</button>
    </div>
</form>
