<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\View;

abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        View::show($view, $data, $layout);
    }

    /**
     * Validates required fields; on failure flashes errors, keeps old input and redirects back.
     * @param array<string,string> $rules field => 'required|email|date|int|max:N'
     */
    protected function validate(array $rules): void
    {
        $errors = [];
        foreach ($rules as $field => $ruleset) {
            $v = input($field);
            foreach (explode('|', $ruleset) as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $empty = $v === null || $v === '';
                $fail = match ($name) {
                    'required' => $empty,
                    'email'    => !$empty && !filter_var($v, FILTER_VALIDATE_EMAIL),
                    'date'     => !$empty && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v),
                    'int'      => !$empty && filter_var($v, FILTER_VALIDATE_INT) === false,
                    'num'      => !$empty && !is_numeric($v),
                    'max'      => !$empty && mb_strlen((string) $v) > (int) $arg,
                    'min'      => !$empty && mb_strlen((string) $v) < (int) $arg,
                    default    => false,
                };
                if ($fail) {
                    $errors[$field] = t('validation.' . $name, ['field' => t('field.' . $field), 'n' => $arg]);
                    break;
                }
            }
        }
        if ($errors) {
            $_SESSION['_old'] = array_map(fn ($v) => is_string($v) ? $v : '', $_POST);
            $_SESSION['_errors'] = $errors;
            foreach ($errors as $msg) {
                flash('error', $msg);
            }
            back();
        }
    }

    /** Picks whitelisted fields from POST, converting '' to null. */
    protected function only(array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            $out[$f] = input_or_null($f);
        }
        return $out;
    }

    protected function isJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    }
}
