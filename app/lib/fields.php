<?php
/**
 * Schema-driven admin form fields.
 * A field definition looks like: ['type' => 'text', 'label' => 'Title', 'required' => true, 'help' => '…']
 * Types: text, textarea, html, code, email, url, number, date, datetime, slug, select, checkbox,
 *        image, icon, lines (one item per line → array), list (repeater of sub-fields), password, heading.
 */
declare(strict_types=1);

function field_options(array $def): array
{
    $o = $def['options'] ?? [];
    return is_callable($o) ? $o() : $o;
}

function render_fields(array $schema, array $values, array $errors = [], string $prefix = ''): string
{
    $html = '';
    foreach ($schema as $key => $def) {
        $name = $prefix === '' ? $key : "{$prefix}[{$key}]";
        $html .= render_field($name, $def, $values[$key] ?? ($def['default'] ?? null), $errors[$key] ?? null, $key);
    }
    return $html;
}

function render_field(string $name, array $def, mixed $value, ?string $error = null, string $key = ''): string
{
    $type = $def['type'] ?? 'text';
    $id = 'f_' . preg_replace('~[^a-z0-9_]+~i', '_', $name);
    $label = $def['label'] ?? ucfirst(str_replace('_', ' ', $key));
    $req = !empty($def['required']);
    $wide = !empty($def['wide']) || in_array($type, ['html', 'list', 'textarea', 'lines', 'code', 'image', 'document', 'heading'], true) && empty($def['half']);
    $help = isset($def['help']) ? '<p class="adm-help">' . $def['help'] . '</p>' : '';
    $err = $error ? '<p class="adm-error">' . e($error) . '</p>' : '';
    $ph = isset($def['placeholder']) ? ' placeholder="' . e($def['placeholder']) . '"' : '';
    $max = isset($def['max']) ? ' maxlength="' . (int) $def['max'] . '"' : '';
    $reqAttr = $req ? ' required' : '';
    $labelHtml = '<label class="adm-label" for="' . $id . '">' . e($label) . ($req ? ' <span class="text-safety">*</span>' : '') . '</label>';
    $extra = $def['attrs'] ?? '';

    $control = match ($type) {
        'heading' => '',
        'textarea', 'code' => '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . (int) ($def['rows'] ?? ($type === 'code' ? 8 : 4)) . '" class="adm-input' . ($type === 'code' ? ' font-mono text-xs' : '') . '"' . $ph . $max . $reqAttr . ' ' . $extra . '>' . e($value) . '</textarea>',
        'html' => '<textarea id="' . $id . '" name="' . e($name) . '" class="adm-input js-editor" rows="12" data-editor="' . e($def['toolbar'] ?? 'full') . '">' . e($value) . '</textarea>',
        'lines' => '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . (int) ($def['rows'] ?? 5) . '" class="adm-input"' . $ph . '>' . e(is_array($value) ? implode("\n", $value) : (string) $value) . '</textarea>',
        'select' => (function () use ($id, $name, $def, $value, $reqAttr, $extra) {
            $h = '<select id="' . $id . '" name="' . e($name) . '" class="adm-input"' . $reqAttr . ' ' . $extra . '>';
            if (array_key_exists('empty', $def)) $h .= '<option value="">' . e($def['empty']) . '</option>';
            foreach (field_options($def) as $k => $l) {
                $h .= '<option value="' . e($k) . '"' . ((string) $k === (string) $value ? ' selected' : '') . '>' . e($l) . '</option>';
            }
            return $h . '</select>';
        })(),
        'checkbox' => '<input type="hidden" name="' . e($name) . '" value="0"><label class="adm-check"><input id="' . $id . '" type="checkbox" name="' . e($name) . '" value="1"' . ($value ? ' checked' : '') . '><span>' . e($def['text'] ?? $label) . '</span></label>',
        'image' => '<div class="adm-image" data-image-field>'
            . '<div class="adm-image-preview" data-preview>' . ($value ? '<img src="' . e(media_url((string) $value)) . '" alt="">' : icon('image', 'h-6 w-6 opacity-40')) . '</div>'
            . '<div class="flex-1 space-y-2"><input id="' . $id . '" type="text" name="' . e($name) . '" value="' . e($value) . '" class="adm-input" placeholder="https://… or uploads/…" data-image-input>'
            . '<div class="flex flex-wrap gap-2"><button type="button" class="adm-btn adm-btn-light adm-btn-sm" data-media-pick>' . icon('images', 'h-4 w-4') . ' Library</button>'
            . '<label class="adm-btn adm-btn-light adm-btn-sm cursor-pointer">' . icon('upload', 'h-4 w-4') . ' Upload<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only" data-media-upload></label>'
            . '<button type="button" class="adm-btn adm-btn-light adm-btn-sm" data-image-clear>' . icon('x', 'h-4 w-4') . ' Clear</button></div></div></div>',
        'document' => '<div class="adm-image" data-image-field data-kind="pdf">'
            . '<div class="adm-image-preview" data-preview>' . ($value ? '<a href="' . e(media_url((string) $value)) . '" target="_blank" class="text-center text-[11px] font-bold text-red-700">' . icon('file-text', 'mx-auto h-6 w-6') . 'PDF</a>' : icon('file-text', 'h-6 w-6 opacity-40')) . '</div>'
            . '<div class="flex-1 space-y-2"><input id="' . $id . '" type="text" name="' . e($name) . '" value="' . e($value) . '" class="adm-input" placeholder="uploads/docs/… .pdf or https://…" data-image-input>'
            . '<div class="flex flex-wrap gap-2"><button type="button" class="adm-btn adm-btn-light adm-btn-sm" data-media-pick>' . icon('files', 'h-4 w-4') . ' Library</button>'
            . '<label class="adm-btn adm-btn-light adm-btn-sm cursor-pointer">' . icon('upload', 'h-4 w-4') . ' Upload PDF<input type="file" accept="application/pdf" class="sr-only" data-media-upload></label>'
            . '<button type="button" class="adm-btn adm-btn-light adm-btn-sm" data-image-clear>' . icon('x', 'h-4 w-4') . ' Clear</button></div></div></div>',
        'icon' => '<div class="flex items-center gap-3"><span class="adm-icon-preview" data-icon-preview>' . icon((string) ($value ?: 'circle'), 'h-5 w-5') . '</span><input id="' . $id . '" type="text" name="' . e($name) . '" value="' . e($value) . '" class="adm-input" list="lucide-icons" data-icon-input placeholder="e.g. wrench"></div>',
        'list' => render_list_field($name, $def, is_array($value) ? $value : []),
        'password' => '<input id="' . $id . '" type="password" name="' . e($name) . '" class="adm-input" autocomplete="new-password"' . $ph . '>',
        'number' => '<input id="' . $id . '" type="number" name="' . e($name) . '" value="' . e($value) . '" class="adm-input"' . $ph . $reqAttr . (isset($def['step']) ? ' step="' . e($def['step']) . '"' : '') . ' ' . $extra . '>',
        'date' => '<input id="' . $id . '" type="date" name="' . e($name) . '" value="' . e($value ? substr((string) $value, 0, 10) : '') . '" class="adm-input"' . $reqAttr . '>',
        'datetime' => '<input id="' . $id . '" type="datetime-local" name="' . e($name) . '" value="' . e($value ? date('Y-m-d\TH:i', (int) strtotime((string) $value)) : '') . '" class="adm-input"' . $reqAttr . '>',
        default => '<input id="' . $id . '" type="' . ($type === 'email' ? 'email' : 'text') . '" name="' . e($name) . '" value="' . e($value) . '" class="adm-input"' . $ph . $max . $reqAttr . ($type === 'slug' ? ' data-slug-input pattern="[a-z0-9-]*"' : '') . ' ' . $extra . '>',
    };

    if ($type === 'heading') {
        return '<div class="adm-field sm:col-span-2 pt-2"><h3 class="adm-subheading">' . e($label) . '</h3>' . $help . '</div>';
    }
    if ($type === 'checkbox') {
        return '<div class="adm-field' . ($wide ? ' sm:col-span-2' : '') . '">' . $control . $help . $err . '</div>';
    }
    $counter = isset($def['counter']) ? ' data-counter="' . (int) $def['counter'] . '"' : '';
    return '<div class="adm-field' . ($wide ? ' sm:col-span-2' : '') . ($error ? ' has-error' : '') . '"' . $counter . '>' . $labelHtml . $control . $help . $err . '</div>';
}

function render_list_field(string $name, array $def, array $rows): string
{
    $sub = $def['fields'] ?? [];
    $rowHtml = function ($index, array $row) use ($name, $sub, $def) {
        $inner = '';
        foreach ($sub as $k => $sd) {
            $sd['wide'] = $sd['wide'] ?? in_array($sd['type'] ?? 'text', ['textarea', 'lines', 'html'], true);
            $inner .= render_field("{$name}[{$index}][{$k}]", $sd + ['label' => ucfirst($k)], $row[$k] ?? null, null, $k);
        }
        return '<div class="adm-repeater-row" data-row>'
            . '<div class="adm-repeater-tools"><span class="adm-repeater-handle">' . icon('grip-vertical', 'h-4 w-4') . '<span data-row-label>' . e($def['item'] ?? 'Item') . '</span></span>'
            . '<span class="flex gap-1"><button type="button" class="adm-icon-btn" data-row-up title="Move up">' . icon('arrow-up', 'h-4 w-4') . '</button>'
            . '<button type="button" class="adm-icon-btn" data-row-down title="Move down">' . icon('arrow-down', 'h-4 w-4') . '</button>'
            . '<button type="button" class="adm-icon-btn text-red-600" data-row-remove title="Remove">' . icon('trash-2', 'h-4 w-4') . '</button></span></div>'
            . '<div class="grid gap-4 sm:grid-cols-2">' . $inner . '</div></div>';
    };
    $html = '<div class="adm-repeater" data-repeater data-name="' . e($name) . '"><div class="space-y-3" data-rows>';
    foreach (array_values($rows) as $i => $r) $html .= $rowHtml($i, is_array($r) ? $r : []);
    $html .= '</div><template data-row-template>' . $rowHtml('__INDEX__', []) . '</template>'
        . '<button type="button" class="adm-btn adm-btn-light adm-btn-sm mt-3" data-row-add>' . icon('plus', 'h-4 w-4') . ' Add ' . e(strtolower($def['item'] ?? 'item')) . '</button></div>';
    return $html;
}

/** Parse + validate POSTed values for a schema. Returns [values, errors]. */
function parse_fields(array $schema, array $input): array
{
    $values = [];
    $errors = [];
    foreach ($schema as $key => $def) {
        $type = $def['type'] ?? 'text';
        if ($type === 'heading') continue;
        $raw = $input[$key] ?? null;
        [$v, $err] = parse_field_value($def, $raw);
        if (!$err && !empty($def['required']) && ($v === null || $v === '' || $v === [])) $err = ($def['label'] ?? ucfirst($key)) . ' is required.';
        if ($err) $errors[$key] = $err;
        if ($type === 'password' && ($v === null || $v === '')) continue; // keep existing secret
        $values[$key] = $v;
    }
    return [$values, $errors];
}

function parse_field_value(array $def, mixed $raw): array
{
    $type = $def['type'] ?? 'text';
    $s = is_string($raw) ? trim($raw) : '';
    switch ($type) {
        case 'checkbox':
            return [(bool) (is_array($raw) ? end($raw) : $raw), null];
        case 'number':
            if ($s === '') return [null, null];
            if (!is_numeric($s)) return [null, 'Must be a number.'];
            return [str_contains($s, '.') ? (float) $s : (int) $s, null];
        case 'lines':
            return [array_values(array_filter(array_map('trim', preg_split('~\R~', $s) ?: []), fn($l) => $l !== '')), null];
        case 'list':
            $rows = [];
            foreach ((is_array($raw) ? $raw : []) as $row) {
                if (!is_array($row)) continue;
                [$vals] = parse_fields($def['fields'] ?? [], $row);
                $nonEmpty = array_filter($vals, fn($v) => $v !== '' && $v !== null && $v !== [] && $v !== false);
                if ($nonEmpty) $rows[] = $vals;
            }
            return [$rows, null];
        case 'html':
            return [sanitize_html(is_string($raw) ? $raw : ''), null];
        case 'code':
            return [is_string($raw) ? $raw : '', null];
        case 'email':
            if ($s !== '' && !filter_var($s, FILTER_VALIDATE_EMAIL)) return [$s, 'Enter a valid email address.'];
            return [$s, null];
        case 'url':
        case 'image':
        case 'document':
            if ($s !== '' && preg_match('~^[a-z][a-z0-9+.-]*:~i', $s) && !preg_match('~^https?://~i', $s)) return [$s, 'Enter an http(s) URL or a site path.'];
            return [$s, null];
        case 'slug':
            return [$s === '' ? '' : slugify($s), null];
        case 'date':
            if ($s === '') return [null, null];
            $d = DateTime::createFromFormat('Y-m-d', $s);
            return $d ? [$d->format('Y-m-d'), null] : [null, 'Enter a valid date.'];
        case 'datetime':
            if ($s === '') return [null, null];
            $ts = strtotime($s);
            return $ts ? [date('Y-m-d H:i:s', $ts), null] : [null, 'Enter a valid date and time.'];
        case 'select':
            $opts = field_options($def);
            if ($s === '') return [null, null];
            return array_key_exists($s, $opts) ? [$s, null] : [null, 'Choose a valid option.'];
        case 'password':
            return [is_string($raw) ? $raw : '', null];
        default:
            if (isset($def['max']) && mb_strlen($s) > (int) $def['max']) return [$s, 'Too long (max ' . (int) $def['max'] . ' characters).'];
            return [$s, null];
    }
}
