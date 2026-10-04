<?php

namespace App\Http\Requests\Settings;

use App\Models\ApplicationSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class UpdateSeoSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', ApplicationSetting::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $rules = [
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'robots_index' => ['nullable', 'in:index,noindex'],
            'robots_follow' => ['nullable', 'in:follow,nofollow'],
            'include_in_sitemap' => ['nullable', 'in:yes,no'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:1000'],
            'og_image' => ['nullable', 'url:http,https', 'max:2048'],
            'og_image_alt' => ['nullable', 'string', 'max:420'],
            'og_type' => ['nullable', 'in:website,article'],
            'twitter_card' => ['nullable', 'in:summary,summary_large_image'],
            'twitter_title' => ['nullable', 'string', 'max:255'],
            'twitter_description' => ['nullable', 'string', 'max:1000'],
            'twitter_image' => ['nullable', 'url:http,https', 'max:2048'],
            'structured_data' => ['nullable', 'string', 'json', 'max:20000'],
        ];
        if ($this->route('target') === 'defaults') {
            $rules = array_diff_key($rules, array_flip(['canonical_url', 'og_title', 'og_description', 'twitter_title', 'twitter_description']));
            $rules += [
                'site_name' => ['required', 'string', 'max:100'],
                'title_suffix' => ['nullable', 'string', 'max:100'],
                'locale' => ['required', 'regex:/^[a-z]{2}_[A-Z]{2}$/'],
                'twitter_site' => ['nullable', 'regex:/^@[A-Za-z0-9_]{1,15}$/'],
                'google_verification' => ['nullable', 'string', 'max:255'],
                'bing_verification' => ['nullable', 'string', 'max:255'],
            ];
            foreach (['meta_title', 'robots_index', 'robots_follow', 'include_in_sitemap', 'og_type', 'twitter_card'] as $field) {
                $rules[$field][0] = 'required';
            }
        }

        return $rules;
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $json = $this->input('structured_data');
            if (is_string($json) && $json !== '' && ! $validator->errors()->has('structured_data')) {
                $schema = json_decode($json, true);
                if (! is_array($schema) || array_is_list($schema) || ($schema['@context'] ?? null) !== 'https://schema.org'
                    || ! is_string($schema['@type'] ?? null) || $schema['@type'] === ''
                    || in_array(null, Arr::flatten($schema), true) || in_array('', Arr::flatten($schema), true)) {
                    $validator->errors()->add('structured_data', 'Saisissez un objet JSON-LD avec @context « https://schema.org » et un @type, sans valeur vide ou nulle.');
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function settings(): array
    {
        $settings = [];
        foreach (array_keys($this->rules()) as $key) {
            $settings[$key] = (string) $this->validated($key, '');
        }

        return $settings;
    }
}
