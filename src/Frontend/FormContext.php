<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\DataLayer;
use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\MealPlan;
use FP\Resv\Domain\Settings\Options;
use FP\Resv\Domain\Settings\Style;
use function apply_filters;
use function array_key_exists;
use function array_keys;
use function array_map;
use function explode;
use function esc_url_raw;
use function json_decode;
use function is_array;
use function is_numeric;
use function is_string;
use function preg_split;
use function preg_replace;
use function implode;
use function sanitize_html_class;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function sort;
use function trim;
use function strtoupper;
use function ucwords;
use function wp_strip_all_tags;

final class FormContext
{
    private Options $options;
    private Language $language;

    /**
     * @var array<string, mixed>
     */
    private array $attributes;

    /**
     * @var array<int, array<string, string>>
     */
    private const DEFAULT_PHONE_PREFIXES = [
        [
            'prefix' => '+39',
            'value' => '39',
            'language' => 'IT',
            'label' => '+39 · Italy',
        ],
        [
            'prefix' => '+1',
            'value' => '1',
            'language' => 'INT',
            'label' => '+1 · Canada',
        ],
        [
            'prefix' => '+1',
            'value' => '1',
            'language' => 'INT',
            'label' => '+1 · United States',
        ],
        [
            'prefix' => '+20',
            'value' => '20',
            'language' => 'INT',
            'label' => '+20 · Egypt',
        ],
        [
            'prefix' => '+27',
            'value' => '27',
            'language' => 'INT',
            'label' => '+27 · South Africa',
        ],
        [
            'prefix' => '+30',
            'value' => '30',
            'language' => 'INT',
            'label' => '+30 · Greece',
        ],
        [
            'prefix' => '+31',
            'value' => '31',
            'language' => 'INT',
            'label' => '+31 · Netherlands',
        ],
        [
            'prefix' => '+32',
            'value' => '32',
            'language' => 'INT',
            'label' => '+32 · Belgium',
        ],
        [
            'prefix' => '+33',
            'value' => '33',
            'language' => 'INT',
            'label' => '+33 · France',
        ],
        [
            'prefix' => '+34',
            'value' => '34',
            'language' => 'INT',
            'label' => '+34 · Spain',
        ],
        [
            'prefix' => '+36',
            'value' => '36',
            'language' => 'INT',
            'label' => '+36 · Hungary',
        ],
        [
            'prefix' => '+40',
            'value' => '40',
            'language' => 'INT',
            'label' => '+40 · Romania',
        ],
        [
            'prefix' => '+41',
            'value' => '41',
            'language' => 'INT',
            'label' => '+41 · Switzerland',
        ],
        [
            'prefix' => '+43',
            'value' => '43',
            'language' => 'INT',
            'label' => '+43 · Austria',
        ],
        [
            'prefix' => '+44',
            'value' => '44',
            'language' => 'INT',
            'label' => '+44 · Guernsey',
        ],
        [
            'prefix' => '+44',
            'value' => '44',
            'language' => 'INT',
            'label' => '+44 · Isle of Man',
        ],
        [
            'prefix' => '+44',
            'value' => '44',
            'language' => 'INT',
            'label' => '+44 · Jersey',
        ],
        [
            'prefix' => '+44',
            'value' => '44',
            'language' => 'INT',
            'label' => '+44 · United Kingdom',
        ],
        [
            'prefix' => '+45',
            'value' => '45',
            'language' => 'INT',
            'label' => '+45 · Denmark',
        ],
        [
            'prefix' => '+46',
            'value' => '46',
            'language' => 'INT',
            'label' => '+46 · Sweden',
        ],
        [
            'prefix' => '+47',
            'value' => '47',
            'language' => 'INT',
            'label' => '+47 · Bouvet Island',
        ],
        [
            'prefix' => '+47',
            'value' => '47',
            'language' => 'INT',
            'label' => '+47 · Norway',
        ],
        [
            'prefix' => '+48',
            'value' => '48',
            'language' => 'INT',
            'label' => '+48 · Poland',
        ],
        [
            'prefix' => '+49',
            'value' => '49',
            'language' => 'INT',
            'label' => '+49 · Germany',
        ],
        [
            'prefix' => '+51',
            'value' => '51',
            'language' => 'INT',
            'label' => '+51 · Peru',
        ],
        [
            'prefix' => '+52',
            'value' => '52',
            'language' => 'INT',
            'label' => '+52 · Mexico',
        ],
        [
            'prefix' => '+53',
            'value' => '53',
            'language' => 'INT',
            'label' => '+53 · Cuba',
        ],
        [
            'prefix' => '+54',
            'value' => '54',
            'language' => 'INT',
            'label' => '+54 · Argentina',
        ],
        [
            'prefix' => '+55',
            'value' => '55',
            'language' => 'INT',
            'label' => '+55 · Brazil',
        ],
        [
            'prefix' => '+56',
            'value' => '56',
            'language' => 'INT',
            'label' => '+56 · Chile',
        ],
        [
            'prefix' => '+57',
            'value' => '57',
            'language' => 'INT',
            'label' => '+57 · Colombia',
        ],
        [
            'prefix' => '+58',
            'value' => '58',
            'language' => 'INT',
            'label' => '+58 · Venezuela',
        ],
        [
            'prefix' => '+60',
            'value' => '60',
            'language' => 'INT',
            'label' => '+60 · Malaysia',
        ],
        [
            'prefix' => '+61',
            'value' => '61',
            'language' => 'INT',
            'label' => '+61 · Australia',
        ],
        [
            'prefix' => '+61',
            'value' => '61',
            'language' => 'INT',
            'label' => '+61 · Christmas Island',
        ],
        [
            'prefix' => '+61',
            'value' => '61',
            'language' => 'INT',
            'label' => '+61 · Cocos (Keeling) Islands',
        ],
        [
            'prefix' => '+62',
            'value' => '62',
            'language' => 'INT',
            'label' => '+62 · Indonesia',
        ],
        [
            'prefix' => '+63',
            'value' => '63',
            'language' => 'INT',
            'label' => '+63 · Philippines',
        ],
        [
            'prefix' => '+64',
            'value' => '64',
            'language' => 'INT',
            'label' => '+64 · New Zealand',
        ],
        [
            'prefix' => '+64',
            'value' => '64',
            'language' => 'INT',
            'label' => '+64 · Pitcairn Islands',
        ],
        [
            'prefix' => '+65',
            'value' => '65',
            'language' => 'INT',
            'label' => '+65 · Singapore',
        ],
        [
            'prefix' => '+66',
            'value' => '66',
            'language' => 'INT',
            'label' => '+66 · Thailand',
        ],
        [
            'prefix' => '+73',
            'value' => '73',
            'language' => 'INT',
            'label' => '+73 · Russia',
        ],
        [
            'prefix' => '+74',
            'value' => '74',
            'language' => 'INT',
            'label' => '+74 · Russia',
        ],
        [
            'prefix' => '+75',
            'value' => '75',
            'language' => 'INT',
            'label' => '+75 · Russia',
        ],
        [
            'prefix' => '+76',
            'value' => '76',
            'language' => 'INT',
            'label' => '+76 · Kazakhstan',
        ],
        [
            'prefix' => '+77',
            'value' => '77',
            'language' => 'INT',
            'label' => '+77 · Kazakhstan',
        ],
        [
            'prefix' => '+78',
            'value' => '78',
            'language' => 'INT',
            'label' => '+78 · Russia',
        ],
        [
            'prefix' => '+79',
            'value' => '79',
            'language' => 'INT',
            'label' => '+79 · Russia',
        ],
        [
            'prefix' => '+81',
            'value' => '81',
            'language' => 'INT',
            'label' => '+81 · Japan',
        ],
        [
            'prefix' => '+82',
            'value' => '82',
            'language' => 'INT',
            'label' => '+82 · South Korea',
        ],
        [
            'prefix' => '+84',
            'value' => '84',
            'language' => 'INT',
            'label' => '+84 · Vietnam',
        ],
        [
            'prefix' => '+86',
            'value' => '86',
            'language' => 'INT',
            'label' => '+86 · China',
        ],
        [
            'prefix' => '+90',
            'value' => '90',
            'language' => 'INT',
            'label' => '+90 · Türkiye',
        ],
        [
            'prefix' => '+91',
            'value' => '91',
            'language' => 'INT',
            'label' => '+91 · India',
        ],
        [
            'prefix' => '+92',
            'value' => '92',
            'language' => 'INT',
            'label' => '+92 · Pakistan',
        ],
        [
            'prefix' => '+93',
            'value' => '93',
            'language' => 'INT',
            'label' => '+93 · Afghanistan',
        ],
        [
            'prefix' => '+94',
            'value' => '94',
            'language' => 'INT',
            'label' => '+94 · Sri Lanka',
        ],
        [
            'prefix' => '+95',
            'value' => '95',
            'language' => 'INT',
            'label' => '+95 · Myanmar',
        ],
        [
            'prefix' => '+98',
            'value' => '98',
            'language' => 'INT',
            'label' => '+98 · Iran',
        ],
        [
            'prefix' => '+211',
            'value' => '211',
            'language' => 'INT',
            'label' => '+211 · South Sudan',
        ],
        [
            'prefix' => '+212',
            'value' => '212',
            'language' => 'INT',
            'label' => '+212 · Morocco',
        ],
        [
            'prefix' => '+213',
            'value' => '213',
            'language' => 'INT',
            'label' => '+213 · Algeria',
        ],
        [
            'prefix' => '+216',
            'value' => '216',
            'language' => 'INT',
            'label' => '+216 · Tunisia',
        ],
        [
            'prefix' => '+218',
            'value' => '218',
            'language' => 'INT',
            'label' => '+218 · Libya',
        ],
        [
            'prefix' => '+220',
            'value' => '220',
            'language' => 'INT',
            'label' => '+220 · Gambia',
        ],
        [
            'prefix' => '+221',
            'value' => '221',
            'language' => 'INT',
            'label' => '+221 · Senegal',
        ],
        [
            'prefix' => '+222',
            'value' => '222',
            'language' => 'INT',
            'label' => '+222 · Mauritania',
        ],
        [
            'prefix' => '+223',
            'value' => '223',
            'language' => 'INT',
            'label' => '+223 · Mali',
        ],
        [
            'prefix' => '+224',
            'value' => '224',
            'language' => 'INT',
            'label' => '+224 · Guinea',
        ],
        [
            'prefix' => '+225',
            'value' => '225',
            'language' => 'INT',
            'label' => '+225 · Ivory Coast',
        ],
        [
            'prefix' => '+226',
            'value' => '226',
            'language' => 'INT',
            'label' => '+226 · Burkina Faso',
        ],
        [
            'prefix' => '+227',
            'value' => '227',
            'language' => 'INT',
            'label' => '+227 · Niger',
        ],
        [
            'prefix' => '+228',
            'value' => '228',
            'language' => 'INT',
            'label' => '+228 · Togo',
        ],
        [
            'prefix' => '+229',
            'value' => '229',
            'language' => 'INT',
            'label' => '+229 · Benin',
        ],
        [
            'prefix' => '+230',
            'value' => '230',
            'language' => 'INT',
            'label' => '+230 · Mauritius',
        ],
        [
            'prefix' => '+231',
            'value' => '231',
            'language' => 'INT',
            'label' => '+231 · Liberia',
        ],
        [
            'prefix' => '+232',
            'value' => '232',
            'language' => 'INT',
            'label' => '+232 · Sierra Leone',
        ],
        [
            'prefix' => '+233',
            'value' => '233',
            'language' => 'INT',
            'label' => '+233 · Ghana',
        ],
        [
            'prefix' => '+234',
            'value' => '234',
            'language' => 'INT',
            'label' => '+234 · Nigeria',
        ],
        [
            'prefix' => '+235',
            'value' => '235',
            'language' => 'INT',
            'label' => '+235 · Chad',
        ],
        [
            'prefix' => '+236',
            'value' => '236',
            'language' => 'INT',
            'label' => '+236 · Central African Republic',
        ],
        [
            'prefix' => '+237',
            'value' => '237',
            'language' => 'INT',
            'label' => '+237 · Cameroon',
        ],
        [
            'prefix' => '+238',
            'value' => '238',
            'language' => 'INT',
            'label' => '+238 · Cape Verde',
        ],
        [
            'prefix' => '+239',
            'value' => '239',
            'language' => 'INT',
            'label' => '+239 · São Tomé and Príncipe',
        ],
        [
            'prefix' => '+240',
            'value' => '240',
            'language' => 'INT',
            'label' => '+240 · Equatorial Guinea',
        ],
        [
            'prefix' => '+241',
            'value' => '241',
            'language' => 'INT',
            'label' => '+241 · Gabon',
        ],
        [
            'prefix' => '+242',
            'value' => '242',
            'language' => 'INT',
            'label' => '+242 · Congo',
        ],
        [
            'prefix' => '+243',
            'value' => '243',
            'language' => 'INT',
            'label' => '+243 · DR Congo',
        ],
        [
            'prefix' => '+244',
            'value' => '244',
            'language' => 'INT',
            'label' => '+244 · Angola',
        ],
        [
            'prefix' => '+245',
            'value' => '245',
            'language' => 'INT',
            'label' => '+245 · Guinea-Bissau',
        ],
        [
            'prefix' => '+246',
            'value' => '246',
            'language' => 'INT',
            'label' => '+246 · British Indian Ocean Territory',
        ],
        [
            'prefix' => '+247',
            'value' => '247',
            'language' => 'INT',
            'label' => '+247 · Saint Helena, Ascension and Tristan da Cunha',
        ],
        [
            'prefix' => '+248',
            'value' => '248',
            'language' => 'INT',
            'label' => '+248 · Seychelles',
        ],
        [
            'prefix' => '+249',
            'value' => '249',
            'language' => 'INT',
            'label' => '+249 · Sudan',
        ],
        [
            'prefix' => '+250',
            'value' => '250',
            'language' => 'INT',
            'label' => '+250 · Rwanda',
        ],
        [
            'prefix' => '+251',
            'value' => '251',
            'language' => 'INT',
            'label' => '+251 · Ethiopia',
        ],
        [
            'prefix' => '+252',
            'value' => '252',
            'language' => 'INT',
            'label' => '+252 · Somalia',
        ],
        [
            'prefix' => '+253',
            'value' => '253',
            'language' => 'INT',
            'label' => '+253 · Djibouti',
        ],
        [
            'prefix' => '+254',
            'value' => '254',
            'language' => 'INT',
            'label' => '+254 · Kenya',
        ],
        [
            'prefix' => '+255',
            'value' => '255',
            'language' => 'INT',
            'label' => '+255 · Tanzania',
        ],
        [
            'prefix' => '+256',
            'value' => '256',
            'language' => 'INT',
            'label' => '+256 · Uganda',
        ],
        [
            'prefix' => '+257',
            'value' => '257',
            'language' => 'INT',
            'label' => '+257 · Burundi',
        ],
        [
            'prefix' => '+258',
            'value' => '258',
            'language' => 'INT',
            'label' => '+258 · Mozambique',
        ],
        [
            'prefix' => '+260',
            'value' => '260',
            'language' => 'INT',
            'label' => '+260 · Zambia',
        ],
        [
            'prefix' => '+261',
            'value' => '261',
            'language' => 'INT',
            'label' => '+261 · Madagascar',
        ],
        [
            'prefix' => '+262',
            'value' => '262',
            'language' => 'INT',
            'label' => '+262 · French Southern and Antarctic Lands',
        ],
        [
            'prefix' => '+262',
            'value' => '262',
            'language' => 'INT',
            'label' => '+262 · Mayotte',
        ],
        [
            'prefix' => '+262',
            'value' => '262',
            'language' => 'INT',
            'label' => '+262 · Réunion',
        ],
        [
            'prefix' => '+263',
            'value' => '263',
            'language' => 'INT',
            'label' => '+263 · Zimbabwe',
        ],
        [
            'prefix' => '+264',
            'value' => '264',
            'language' => 'INT',
            'label' => '+264 · Namibia',
        ],
        [
            'prefix' => '+265',
            'value' => '265',
            'language' => 'INT',
            'label' => '+265 · Malawi',
        ],
        [
            'prefix' => '+266',
            'value' => '266',
            'language' => 'INT',
            'label' => '+266 · Lesotho',
        ],
        [
            'prefix' => '+267',
            'value' => '267',
            'language' => 'INT',
            'label' => '+267 · Botswana',
        ],
        [
            'prefix' => '+268',
            'value' => '268',
            'language' => 'INT',
            'label' => '+268 · Eswatini',
        ],
        [
            'prefix' => '+268',
            'value' => '268',
            'language' => 'INT',
            'label' => '+268 · United States Minor Outlying Islands',
        ],
        [
            'prefix' => '+269',
            'value' => '269',
            'language' => 'INT',
            'label' => '+269 · Comoros',
        ],
        [
            'prefix' => '+290',
            'value' => '290',
            'language' => 'INT',
            'label' => '+290 · Saint Helena, Ascension and Tristan da Cunha',
        ],
        [
            'prefix' => '+291',
            'value' => '291',
            'language' => 'INT',
            'label' => '+291 · Eritrea',
        ],
        [
            'prefix' => '+297',
            'value' => '297',
            'language' => 'INT',
            'label' => '+297 · Aruba',
        ],
        [
            'prefix' => '+298',
            'value' => '298',
            'language' => 'INT',
            'label' => '+298 · Faroe Islands',
        ],
        [
            'prefix' => '+299',
            'value' => '299',
            'language' => 'INT',
            'label' => '+299 · Greenland',
        ],
        [
            'prefix' => '+350',
            'value' => '350',
            'language' => 'INT',
            'label' => '+350 · Gibraltar',
        ],
        [
            'prefix' => '+351',
            'value' => '351',
            'language' => 'INT',
            'label' => '+351 · Portugal',
        ],
        [
            'prefix' => '+352',
            'value' => '352',
            'language' => 'INT',
            'label' => '+352 · Luxembourg',
        ],
        [
            'prefix' => '+353',
            'value' => '353',
            'language' => 'INT',
            'label' => '+353 · Ireland',
        ],
        [
            'prefix' => '+354',
            'value' => '354',
            'language' => 'INT',
            'label' => '+354 · Iceland',
        ],
        [
            'prefix' => '+355',
            'value' => '355',
            'language' => 'INT',
            'label' => '+355 · Albania',
        ],
        [
            'prefix' => '+356',
            'value' => '356',
            'language' => 'INT',
            'label' => '+356 · Malta',
        ],
        [
            'prefix' => '+357',
            'value' => '357',
            'language' => 'INT',
            'label' => '+357 · Cyprus',
        ],
        [
            'prefix' => '+358',
            'value' => '358',
            'language' => 'INT',
            'label' => '+358 · Finland',
        ],
        [
            'prefix' => '+359',
            'value' => '359',
            'language' => 'INT',
            'label' => '+359 · Bulgaria',
        ],
        [
            'prefix' => '+370',
            'value' => '370',
            'language' => 'INT',
            'label' => '+370 · Lithuania',
        ],
        [
            'prefix' => '+371',
            'value' => '371',
            'language' => 'INT',
            'label' => '+371 · Latvia',
        ],
        [
            'prefix' => '+372',
            'value' => '372',
            'language' => 'INT',
            'label' => '+372 · Estonia',
        ],
        [
            'prefix' => '+373',
            'value' => '373',
            'language' => 'INT',
            'label' => '+373 · Moldova',
        ],
        [
            'prefix' => '+374',
            'value' => '374',
            'language' => 'INT',
            'label' => '+374 · Armenia',
        ],
        [
            'prefix' => '+375',
            'value' => '375',
            'language' => 'INT',
            'label' => '+375 · Belarus',
        ],
        [
            'prefix' => '+376',
            'value' => '376',
            'language' => 'INT',
            'label' => '+376 · Andorra',
        ],
        [
            'prefix' => '+377',
            'value' => '377',
            'language' => 'INT',
            'label' => '+377 · Monaco',
        ],
        [
            'prefix' => '+378',
            'value' => '378',
            'language' => 'INT',
            'label' => '+378 · San Marino',
        ],
        [
            'prefix' => '+379',
            'value' => '379',
            'language' => 'INT',
            'label' => '+379 · Vatican City',
        ],
        [
            'prefix' => '+380',
            'value' => '380',
            'language' => 'INT',
            'label' => '+380 · Ukraine',
        ],
        [
            'prefix' => '+381',
            'value' => '381',
            'language' => 'INT',
            'label' => '+381 · Serbia',
        ],
        [
            'prefix' => '+382',
            'value' => '382',
            'language' => 'INT',
            'label' => '+382 · Montenegro',
        ],
        [
            'prefix' => '+383',
            'value' => '383',
            'language' => 'INT',
            'label' => '+383 · Kosovo',
        ],
        [
            'prefix' => '+385',
            'value' => '385',
            'language' => 'INT',
            'label' => '+385 · Croatia',
        ],
        [
            'prefix' => '+386',
            'value' => '386',
            'language' => 'INT',
            'label' => '+386 · Slovenia',
        ],
        [
            'prefix' => '+387',
            'value' => '387',
            'language' => 'INT',
            'label' => '+387 · Bosnia and Herzegovina',
        ],
        [
            'prefix' => '+389',
            'value' => '389',
            'language' => 'INT',
            'label' => '+389 · North Macedonia',
        ],
        [
            'prefix' => '+420',
            'value' => '420',
            'language' => 'INT',
            'label' => '+420 · Czechia',
        ],
        [
            'prefix' => '+421',
            'value' => '421',
            'language' => 'INT',
            'label' => '+421 · Slovakia',
        ],
        [
            'prefix' => '+423',
            'value' => '423',
            'language' => 'INT',
            'label' => '+423 · Liechtenstein',
        ],
        [
            'prefix' => '+500',
            'value' => '500',
            'language' => 'INT',
            'label' => '+500 · Falkland Islands',
        ],
        [
            'prefix' => '+500',
            'value' => '500',
            'language' => 'INT',
            'label' => '+500 · South Georgia',
        ],
        [
            'prefix' => '+501',
            'value' => '501',
            'language' => 'INT',
            'label' => '+501 · Belize',
        ],
        [
            'prefix' => '+502',
            'value' => '502',
            'language' => 'INT',
            'label' => '+502 · Guatemala',
        ],
        [
            'prefix' => '+503',
            'value' => '503',
            'language' => 'INT',
            'label' => '+503 · El Salvador',
        ],
        [
            'prefix' => '+504',
            'value' => '504',
            'language' => 'INT',
            'label' => '+504 · Honduras',
        ],
        [
            'prefix' => '+505',
            'value' => '505',
            'language' => 'INT',
            'label' => '+505 · Nicaragua',
        ],
        [
            'prefix' => '+506',
            'value' => '506',
            'language' => 'INT',
            'label' => '+506 · Costa Rica',
        ],
        [
            'prefix' => '+507',
            'value' => '507',
            'language' => 'INT',
            'label' => '+507 · Panama',
        ],
        [
            'prefix' => '+508',
            'value' => '508',
            'language' => 'INT',
            'label' => '+508 · Saint Pierre and Miquelon',
        ],
        [
            'prefix' => '+509',
            'value' => '509',
            'language' => 'INT',
            'label' => '+509 · Haiti',
        ],
        [
            'prefix' => '+590',
            'value' => '590',
            'language' => 'INT',
            'label' => '+590 · Guadeloupe',
        ],
        [
            'prefix' => '+590',
            'value' => '590',
            'language' => 'INT',
            'label' => '+590 · Saint Barthélemy',
        ],
        [
            'prefix' => '+590',
            'value' => '590',
            'language' => 'INT',
            'label' => '+590 · Saint Martin',
        ],
        [
            'prefix' => '+591',
            'value' => '591',
            'language' => 'INT',
            'label' => '+591 · Bolivia',
        ],
        [
            'prefix' => '+592',
            'value' => '592',
            'language' => 'INT',
            'label' => '+592 · Guyana',
        ],
        [
            'prefix' => '+593',
            'value' => '593',
            'language' => 'INT',
            'label' => '+593 · Ecuador',
        ],
        [
            'prefix' => '+594',
            'value' => '594',
            'language' => 'INT',
            'label' => '+594 · French Guiana',
        ],
        [
            'prefix' => '+595',
            'value' => '595',
            'language' => 'INT',
            'label' => '+595 · Paraguay',
        ],
        [
            'prefix' => '+596',
            'value' => '596',
            'language' => 'INT',
            'label' => '+596 · Martinique',
        ],
        [
            'prefix' => '+597',
            'value' => '597',
            'language' => 'INT',
            'label' => '+597 · Suriname',
        ],
        [
            'prefix' => '+598',
            'value' => '598',
            'language' => 'INT',
            'label' => '+598 · Uruguay',
        ],
        [
            'prefix' => '+599',
            'value' => '599',
            'language' => 'INT',
            'label' => '+599 · Caribbean Netherlands',
        ],
        [
            'prefix' => '+599',
            'value' => '599',
            'language' => 'INT',
            'label' => '+599 · Curaçao',
        ],
        [
            'prefix' => '+670',
            'value' => '670',
            'language' => 'INT',
            'label' => '+670 · Timor-Leste',
        ],
        [
            'prefix' => '+672',
            'value' => '672',
            'language' => 'INT',
            'label' => '+672 · Norfolk Island',
        ],
        [
            'prefix' => '+673',
            'value' => '673',
            'language' => 'INT',
            'label' => '+673 · Brunei',
        ],
        [
            'prefix' => '+674',
            'value' => '674',
            'language' => 'INT',
            'label' => '+674 · Nauru',
        ],
        [
            'prefix' => '+675',
            'value' => '675',
            'language' => 'INT',
            'label' => '+675 · Papua New Guinea',
        ],
        [
            'prefix' => '+676',
            'value' => '676',
            'language' => 'INT',
            'label' => '+676 · Tonga',
        ],
        [
            'prefix' => '+677',
            'value' => '677',
            'language' => 'INT',
            'label' => '+677 · Solomon Islands',
        ],
        [
            'prefix' => '+678',
            'value' => '678',
            'language' => 'INT',
            'label' => '+678 · Vanuatu',
        ],
        [
            'prefix' => '+679',
            'value' => '679',
            'language' => 'INT',
            'label' => '+679 · Fiji',
        ],
        [
            'prefix' => '+680',
            'value' => '680',
            'language' => 'INT',
            'label' => '+680 · Palau',
        ],
        [
            'prefix' => '+681',
            'value' => '681',
            'language' => 'INT',
            'label' => '+681 · Wallis and Futuna',
        ],
        [
            'prefix' => '+682',
            'value' => '682',
            'language' => 'INT',
            'label' => '+682 · Cook Islands',
        ],
        [
            'prefix' => '+683',
            'value' => '683',
            'language' => 'INT',
            'label' => '+683 · Niue',
        ],
        [
            'prefix' => '+685',
            'value' => '685',
            'language' => 'INT',
            'label' => '+685 · Samoa',
        ],
        [
            'prefix' => '+686',
            'value' => '686',
            'language' => 'INT',
            'label' => '+686 · Kiribati',
        ],
        [
            'prefix' => '+687',
            'value' => '687',
            'language' => 'INT',
            'label' => '+687 · New Caledonia',
        ],
        [
            'prefix' => '+688',
            'value' => '688',
            'language' => 'INT',
            'label' => '+688 · Tuvalu',
        ],
        [
            'prefix' => '+689',
            'value' => '689',
            'language' => 'INT',
            'label' => '+689 · French Polynesia',
        ],
        [
            'prefix' => '+690',
            'value' => '690',
            'language' => 'INT',
            'label' => '+690 · Tokelau',
        ],
        [
            'prefix' => '+691',
            'value' => '691',
            'language' => 'INT',
            'label' => '+691 · Micronesia',
        ],
        [
            'prefix' => '+692',
            'value' => '692',
            'language' => 'INT',
            'label' => '+692 · Marshall Islands',
        ],
        [
            'prefix' => '+850',
            'value' => '850',
            'language' => 'INT',
            'label' => '+850 · North Korea',
        ],
        [
            'prefix' => '+852',
            'value' => '852',
            'language' => 'INT',
            'label' => '+852 · Hong Kong',
        ],
        [
            'prefix' => '+853',
            'value' => '853',
            'language' => 'INT',
            'label' => '+853 · Macau',
        ],
        [
            'prefix' => '+855',
            'value' => '855',
            'language' => 'INT',
            'label' => '+855 · Cambodia',
        ],
        [
            'prefix' => '+856',
            'value' => '856',
            'language' => 'INT',
            'label' => '+856 · Laos',
        ],
        [
            'prefix' => '+880',
            'value' => '880',
            'language' => 'INT',
            'label' => '+880 · Bangladesh',
        ],
        [
            'prefix' => '+886',
            'value' => '886',
            'language' => 'INT',
            'label' => '+886 · Taiwan',
        ],
        [
            'prefix' => '+960',
            'value' => '960',
            'language' => 'INT',
            'label' => '+960 · Maldives',
        ],
        [
            'prefix' => '+961',
            'value' => '961',
            'language' => 'INT',
            'label' => '+961 · Lebanon',
        ],
        [
            'prefix' => '+962',
            'value' => '962',
            'language' => 'INT',
            'label' => '+962 · Jordan',
        ],
        [
            'prefix' => '+963',
            'value' => '963',
            'language' => 'INT',
            'label' => '+963 · Syria',
        ],
        [
            'prefix' => '+964',
            'value' => '964',
            'language' => 'INT',
            'label' => '+964 · Iraq',
        ],
        [
            'prefix' => '+965',
            'value' => '965',
            'language' => 'INT',
            'label' => '+965 · Kuwait',
        ],
        [
            'prefix' => '+966',
            'value' => '966',
            'language' => 'INT',
            'label' => '+966 · Saudi Arabia',
        ],
        [
            'prefix' => '+967',
            'value' => '967',
            'language' => 'INT',
            'label' => '+967 · Yemen',
        ],
        [
            'prefix' => '+968',
            'value' => '968',
            'language' => 'INT',
            'label' => '+968 · Oman',
        ],
        [
            'prefix' => '+970',
            'value' => '970',
            'language' => 'INT',
            'label' => '+970 · Palestine',
        ],
        [
            'prefix' => '+971',
            'value' => '971',
            'language' => 'INT',
            'label' => '+971 · United Arab Emirates',
        ],
        [
            'prefix' => '+972',
            'value' => '972',
            'language' => 'INT',
            'label' => '+972 · Israel',
        ],
        [
            'prefix' => '+973',
            'value' => '973',
            'language' => 'INT',
            'label' => '+973 · Bahrain',
        ],
        [
            'prefix' => '+974',
            'value' => '974',
            'language' => 'INT',
            'label' => '+974 · Qatar',
        ],
        [
            'prefix' => '+975',
            'value' => '975',
            'language' => 'INT',
            'label' => '+975 · Bhutan',
        ],
        [
            'prefix' => '+976',
            'value' => '976',
            'language' => 'INT',
            'label' => '+976 · Mongolia',
        ],
        [
            'prefix' => '+977',
            'value' => '977',
            'language' => 'INT',
            'label' => '+977 · Nepal',
        ],
        [
            'prefix' => '+992',
            'value' => '992',
            'language' => 'INT',
            'label' => '+992 · Tajikistan',
        ],
        [
            'prefix' => '+993',
            'value' => '993',
            'language' => 'INT',
            'label' => '+993 · Turkmenistan',
        ],
        [
            'prefix' => '+994',
            'value' => '994',
            'language' => 'INT',
            'label' => '+994 · Azerbaijan',
        ],
        [
            'prefix' => '+995',
            'value' => '995',
            'language' => 'INT',
            'label' => '+995 · Georgia',
        ],
        [
            'prefix' => '+996',
            'value' => '996',
            'language' => 'INT',
            'label' => '+996 · Kyrgyzstan',
        ],
        [
            'prefix' => '+998',
            'value' => '998',
            'language' => 'INT',
            'label' => '+998 · Uzbekistan',
        ],
        [
            'prefix' => '+1242',
            'value' => '1242',
            'language' => 'INT',
            'label' => '+1242 · Bahamas',
        ],
        [
            'prefix' => '+1246',
            'value' => '1246',
            'language' => 'INT',
            'label' => '+1246 · Barbados',
        ],
        [
            'prefix' => '+1264',
            'value' => '1264',
            'language' => 'INT',
            'label' => '+1264 · Anguilla',
        ],
        [
            'prefix' => '+1268',
            'value' => '1268',
            'language' => 'INT',
            'label' => '+1268 · Antigua and Barbuda',
        ],
        [
            'prefix' => '+1284',
            'value' => '1284',
            'language' => 'INT',
            'label' => '+1284 · British Virgin Islands',
        ],
        [
            'prefix' => '+1340',
            'value' => '1340',
            'language' => 'INT',
            'label' => '+1340 · United States Virgin Islands',
        ],
        [
            'prefix' => '+1345',
            'value' => '1345',
            'language' => 'INT',
            'label' => '+1345 · Cayman Islands',
        ],
        [
            'prefix' => '+1441',
            'value' => '1441',
            'language' => 'INT',
            'label' => '+1441 · Bermuda',
        ],
        [
            'prefix' => '+1473',
            'value' => '1473',
            'language' => 'INT',
            'label' => '+1473 · Grenada',
        ],
        [
            'prefix' => '+1649',
            'value' => '1649',
            'language' => 'INT',
            'label' => '+1649 · Turks and Caicos Islands',
        ],
        [
            'prefix' => '+1664',
            'value' => '1664',
            'language' => 'INT',
            'label' => '+1664 · Montserrat',
        ],
        [
            'prefix' => '+1670',
            'value' => '1670',
            'language' => 'INT',
            'label' => '+1670 · Northern Mariana Islands',
        ],
        [
            'prefix' => '+1671',
            'value' => '1671',
            'language' => 'INT',
            'label' => '+1671 · Guam',
        ],
        [
            'prefix' => '+1684',
            'value' => '1684',
            'language' => 'INT',
            'label' => '+1684 · American Samoa',
        ],
        [
            'prefix' => '+1721',
            'value' => '1721',
            'language' => 'INT',
            'label' => '+1721 · Sint Maarten',
        ],
        [
            'prefix' => '+1758',
            'value' => '1758',
            'language' => 'INT',
            'label' => '+1758 · Saint Lucia',
        ],
        [
            'prefix' => '+1767',
            'value' => '1767',
            'language' => 'INT',
            'label' => '+1767 · Dominica',
        ],
        [
            'prefix' => '+1784',
            'value' => '1784',
            'language' => 'INT',
            'label' => '+1784 · Saint Vincent and the Grenadines',
        ],
        [
            'prefix' => '+1787',
            'value' => '1787',
            'language' => 'INT',
            'label' => '+1787 · Puerto Rico',
        ],
        [
            'prefix' => '+1809',
            'value' => '1809',
            'language' => 'INT',
            'label' => '+1809 · Dominican Republic',
        ],
        [
            'prefix' => '+1829',
            'value' => '1829',
            'language' => 'INT',
            'label' => '+1829 · Dominican Republic',
        ],
        [
            'prefix' => '+1849',
            'value' => '1849',
            'language' => 'INT',
            'label' => '+1849 · Dominican Republic',
        ],
        [
            'prefix' => '+1868',
            'value' => '1868',
            'language' => 'INT',
            'label' => '+1868 · Trinidad and Tobago',
        ],
        [
            'prefix' => '+1869',
            'value' => '1869',
            'language' => 'INT',
            'label' => '+1869 · Saint Kitts and Nevis',
        ],
        [
            'prefix' => '+1876',
            'value' => '1876',
            'language' => 'INT',
            'label' => '+1876 · Jamaica',
        ],
        [
            'prefix' => '+1939',
            'value' => '1939',
            'language' => 'INT',
            'label' => '+1939 · Puerto Rico',
        ],
        [
            'prefix' => '+4779',
            'value' => '4779',
            'language' => 'INT',
            'label' => '+4779 · Svalbard and Jan Mayen',
        ],
        [
            'prefix' => '+35818',
            'value' => '35818',
            'language' => 'INT',
            'label' => '+35818 · Åland Islands',
        ],
        [
            'prefix' => '+2125288',
            'value' => '2125288',
            'language' => 'INT',
            'label' => '+2125288 · Western Sahara',
        ],
        [
            'prefix' => '+2125289',
            'value' => '2125289',
            'language' => 'INT',
            'label' => '+2125289 · Western Sahara',
        ],
        [
            'prefix' => '+3906698',
            'value' => '3906698',
            'language' => 'INT',
            'label' => '+3906698 · Vatican City',
        ],
    ];


    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(Options $options, Language $language, array $attributes = [])
    {
        $this->options    = $options;
        $this->language   = $language;
        $this->attributes = $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $generalDefaults = [
            'restaurant_name'             => '',
            'restaurant_timezone'         => 'Europe/Rome',
            'default_party_size'          => '2',
            'default_reservation_status'  => 'pending',
            'default_currency'            => 'EUR',
            'enable_waitlist'             => '0',
            'data_retention_months'       => '24',
        ];

        $languageDefaults = [
            'language_fallback_locale'   => 'it_IT',
            'language_supported_locales' => 'it_IT' . PHP_EOL . 'en_US',
            'pdf_urls'                   => [],
            'language_cookie_days'       => '30',
        ];

        $trackingDefaults = [
            'privacy_policy_url'               => '',
            'privacy_policy_version'           => '1.0',
            'privacy_enable_marketing_consent' => '0',
            'privacy_enable_profiling_consent' => '0',
            'privacy_retention_months'         => '24',
        ];

        $generalSettings  = $this->options->getGroup('fp_resv_general', $generalDefaults);
        $languageSettings = $this->options->getGroup('fp_resv_language', $languageDefaults);
        $trackingSettings = $this->options->getGroup('fp_resv_tracking', $trackingDefaults);

        $supportedLocales = $this->language->getSupportedLocales();
        $languageData     = $this->language->detect([
            'lang'   => $this->attributes['lang'] ?? '',
            'locale' => $languageSettings['language_fallback_locale'] ?? '',
        ]);
        $fallbackLocale   = $this->language->getFallbackLocale();

        $mealDefinition = isset($generalSettings['frontend_meals']) ? (string) $generalSettings['frontend_meals'] : '';
        $rawMeals       = MealPlan::parse($mealDefinition);

        $config = [
            'formId'          => $this->resolveFormId(),
            'location'        => $this->resolveLocation(),
            'locale'          => $languageData['locale'],
            'language'        => $languageData['language'],
            'language_source' => $languageData['source'],
            'timezone'        => $this->normalizeTimezone($generalSettings['restaurant_timezone'] ?? 'Europe/Rome'),
            'defaults'        => [
                'partySize'       => $this->toInt($generalSettings['default_party_size'] ?? 2, 2),
                'status'          => (string) ($generalSettings['default_reservation_status'] ?? 'pending'),
                'currency'        => (string) ($generalSettings['default_currency'] ?? 'EUR'),
                'waitlistEnabled' => ($generalSettings['enable_waitlist'] ?? '0') === '1',
            ],
        ];

        $brevoSettings  = $this->options->getGroup('fp_resv_brevo', []);
        $phonePrefixes  = $this->parsePhonePrefixOptions($brevoSettings['brevo_phone_prefix_map'] ?? null);
        if ($phonePrefixes === []) {
            $phonePrefixes = $this->defaultPhonePrefixes();
        }

        $phonePrefixes = $this->condensePhonePrefixes($phonePrefixes);

        if ($phonePrefixes !== []) {
            $config['phone_prefixes'] = $phonePrefixes;
            $defaultPhoneCode = (string) ($phonePrefixes[0]['value'] ?? '39');

            foreach ($phonePrefixes as $prefixOption) {
                if (($prefixOption['value'] ?? '') === '39') {
                    $defaultPhoneCode = '39';
                    break;
                }
            }

            $config['defaults']['phone_country_code'] = $defaultPhoneCode;
        } else {
            $config['defaults']['phone_country_code'] = '39';
        }

        $meals = MealPlan::normalizeList(apply_filters('fp_resv_form_meals', $rawMeals, $config));
        if ($meals !== []) {
            $defaultMeal = MealPlan::getDefaultKey($meals);
            if ($defaultMeal !== '') {
                $config['defaults']['meal'] = $defaultMeal;
            }
        }

        $dictionary  = $this->language->getStrings($languageData['language']);
        $formStrings = is_array($dictionary['form'] ?? null) ? $dictionary['form'] : [];

        $strings = $this->buildStrings(
            $formStrings,
            (string) ($generalSettings['restaurant_name'] ?? '')
        );

        $privacy = [
            'policy_url'        => esc_url_raw((string) ($trackingSettings['privacy_policy_url'] ?? '')),
            'policy_version'    => trim((string) ($trackingSettings['privacy_policy_version'] ?? '1.0')),
            'marketing_enabled' => ($trackingSettings['privacy_enable_marketing_consent'] ?? '0') === '1',
            'profiling_enabled' => ($trackingSettings['privacy_enable_profiling_consent'] ?? '0') === '1',
            'retention_months'  => (int) ($trackingSettings['privacy_retention_months'] ?? 0),
        ];

        if ($privacy['policy_version'] === '') {
            $privacy['policy_version'] = '1.0';
        }

        $steps = $this->buildSteps(
            is_array($formStrings['step_content'] ?? null) ? $formStrings['step_content'] : [],
            is_array($formStrings['step_order'] ?? null) ? $formStrings['step_order'] : ['date', 'party', 'slots', 'details', 'confirm']
        );
        $pdfUrl = $this->resolvePdfUrl(
            $languageData['language'],
            $languageSettings,
            $supportedLocales,
            $fallbackLocale
        );

        $styleService = new Style($this->options);
        $stylePayload = $styleService->buildFrontend($config['formId']);

        $pdfMapKeys = [];
        if (isset($languageSettings['pdf_urls']) && is_array($languageSettings['pdf_urls'])) {
            $pdfMapKeys = array_keys($languageSettings['pdf_urls']);
        }

        $viewEvent = DataLayer::push([
            'event'       => 'reservation_view',
            'reservation' => [
                'language' => $config['language'],
                'locale'   => $config['locale'],
                'location' => $config['location'],
            ],
            'ga4' => [
                'name'   => 'reservation_view',
                'params' => [
                    'reservation_language' => $config['language'],
                    'reservation_locale'   => $config['locale'],
                    'reservation_location' => $config['location'],
                ],
            ],
        ]);

        $dataLayer = [
            'view'   => $viewEvent,
            'events' => [
                'start'            => 'reservation_start',
                'pdf'              => 'pdf_download_click',
                'submit'           => 'reservation_submit',
                'confirmed'        => 'reservation_confirmed',
                'waitlist'         => 'waitlist_joined',
                'payment_required' => 'reservation_payment_required',
                'cancelled'        => 'reservation_cancelled',
                'modified'         => 'reservation_modified',
                'meal_selected'    => 'meal_selected',
                'section_unlocked' => 'section_unlocked',
                'form_valid'       => 'form_valid',
                'purchase'         => 'purchase',
            ],
        ];

        return [
            'config'      => $config,
            'strings'     => $strings,
            'steps'       => $steps,
            'pdf_url'     => $pdfUrl,
            'data_layer'  => $dataLayer,
            'style'       => $stylePayload,
            'privacy'     => $privacy,
            'meals'       => $meals,
            'meta'        => [
                'supported_locales' => $supportedLocales,
                'pdf_locales'       => $pdfMapKeys,
            ],
        ];
    }

    private function resolveFormId(): string
    {
        $formId = isset($this->attributes['form_id']) ? (string) $this->attributes['form_id'] : '';
        if ($formId === '') {
            $formId = 'fp-resv-' . $this->resolveLocation();
        }

        $sanitized = sanitize_html_class($formId);
        if ($sanitized === '') {
            return 'fp-resv-form';
        }

        return $sanitized;
    }

    private function resolveLocation(): string
    {
        $location = isset($this->attributes['location']) ? strtolower((string) $this->attributes['location']) : 'default';
        $location = preg_replace('/[^a-z0-9_-]+/', '-', $location) ?? 'default';
        $location = trim($location, '-_');

        return $location === '' ? 'default' : $location;
    }

    private function normalizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone);

        return $timezone === '' ? 'Europe/Rome' : $timezone;
    }

    private function toInt(mixed $value, int $fallback): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return $fallback;
    }

    /**
     * @param array<int, string> $supportedLocales
     */
    private function resolvePdfUrl(string $languageSlug, array $languageSettings, array $supportedLocales, string $fallbackLocale): string
    {
        $map = $languageSettings['pdf_urls'] ?? [];
        if (!is_array($map)) {
            return '';
        }

        $languageSlug = sanitize_key($languageSlug);
        if ($languageSlug !== '' && array_key_exists($languageSlug, $map)) {
            return (string) $map[$languageSlug];
        }

        $fallbackSlug = $this->language->languageFromLocale($fallbackLocale);
        if ($fallbackSlug !== '' && array_key_exists($fallbackSlug, $map)) {
            return (string) $map[$fallbackSlug];
        }

        foreach ($supportedLocales as $locale) {
            $slug = $this->language->languageFromLocale($locale);
            if ($slug !== '' && array_key_exists($slug, $map)) {
                return (string) $map[$slug];
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $formStrings
     *
     * @return array<string, mixed>
     */
    private function buildStrings(array $formStrings, string $restaurantName): array
    {
        $headline = $formStrings['headline']['default'] ?? '';
        if ($restaurantName !== '' && isset($formStrings['headline']['with_name'])) {
            $headline = sprintf((string) $formStrings['headline']['with_name'], wp_strip_all_tags($restaurantName));
        }

        return [
            'headline'    => $headline,
            'subheadline' => (string) ($formStrings['subheadline'] ?? ''),
            'pdf_label'   => (string) ($formStrings['pdf_label'] ?? ''),
            'pdf_tooltip' => (string) ($formStrings['pdf_tooltip'] ?? ''),
            'steps'       => is_array($formStrings['steps_labels'] ?? null) ? $formStrings['steps_labels'] : [],
            'fields'      => is_array($formStrings['fields'] ?? null) ? $formStrings['fields'] : [],
            'meals'       => is_array($formStrings['meals'] ?? null) ? $formStrings['meals'] : [],
            'actions'     => is_array($formStrings['actions'] ?? null) ? $formStrings['actions'] : [],
            'summary'     => is_array($formStrings['summary'] ?? null) ? $formStrings['summary'] : [],
            'messages'    => is_array($formStrings['messages'] ?? null) ? $formStrings['messages'] : [],
            'consents'    => is_array($formStrings['consents'] ?? null) ? $formStrings['consents'] : [],
        ];
    }

    /**
     * @param array<string, array<string, string>> $stepContent
     * @param array<int, string> $order
     *
     * @return array<int, array<string, string>>
     */
    private function buildSteps(array $stepContent, array $order): array
    {
        $steps = [];

        foreach ($order as $key) {
            $data = $stepContent[$key] ?? [];
            if (!is_array($data)) {
                $data = [];
            }

            $steps[] = [
                'key'         => (string) $key,
                'title'       => (string) ($data['title'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
            ];
        }

        return $steps;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parsePhonePrefixOptions(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $map = [];

        foreach ($decoded as $prefix => $language) {
            if (!is_string($prefix)) {
                continue;
            }

            $normalizedPrefix = $this->normalizePhonePrefix($prefix);
            if ($normalizedPrefix === '') {
                continue;
            }

            $languageCode = $this->normalizePhoneLanguage(is_string($language) ? $language : '');
            if (!array_key_exists($normalizedPrefix, $map)) {
                $map[$normalizedPrefix] = $languageCode;
            }
        }

        if ($map === []) {
            return [];
        }

        $options = [];

        foreach ($map as $prefix => $language) {
            $digits = preg_replace('/[^0-9]/', '', substr($prefix, 1));
            if (!is_string($digits) || $digits === '') {
                continue;
            }

            $label = $prefix;
            if ($language !== '') {
                $label .= ' · ' . $language;
            }

            $options[] = [
                'prefix'   => $prefix,
                'value'    => $digits,
                'language' => $language,
                'label'    => $label,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function defaultPhonePrefixes(): array
    {
        return $this->condensePhonePrefixes(self::DEFAULT_PHONE_PREFIXES);
    }

    /**
     * @param array<int, array<string, string>> $prefixes
     *
     * @return array<int, array<string, string>>
     */
    private function condensePhonePrefixes(array $prefixes): array
    {
        $groups = [];

        foreach ($prefixes as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $rawPrefix = isset($entry['prefix']) ? (string) $entry['prefix'] : '';
            $prefix    = $this->normalizePhonePrefix($rawPrefix);
            if ($prefix === '') {
                continue;
            }

            $digits = preg_replace('/[^0-9]/', '', substr($prefix, 1));
            if (!is_string($digits) || $digits === '') {
                continue;
            }

            $language = isset($entry['language']) ? (string) $entry['language'] : 'INT';
            $label    = isset($entry['label']) ? (string) $entry['label'] : $prefix;

            $normalizedLabel = str_replace("\u{00A0}", ' ', $label);
            $name            = trim($normalizedLabel);
            $parts           = preg_split('/\s*·\s*/u', $normalizedLabel, 2);
            if (is_array($parts) && count($parts) === 2) {
                $name = trim(str_replace("\u{00A0}", ' ', $parts[1]));
            }

            if (!isset($groups[$digits])) {
                $groups[$digits] = [
                    'prefix'    => $prefix,
                    'value'     => $digits,
                    'language'  => $language,
                    'countries' => [],
                ];
            }

            if ($name !== '') {
                $groups[$digits]['countries'][$name] = true;
            }
        }

        if ($groups === []) {
            return [];
        }

        // Deduplica i paesi che compaiono con prefissi diversi: mantiene
        // la prima occorrenza e scarta le successive per evitare ripetizioni.
        // Si preferiscono gruppi con prefisso più corto (es. +1 rispetto a +1869),
        // in caso di pari lunghezza si usa l'ordinamento numerico del prefisso.

        // Ordina i gruppi per lunghezza prefisso e poi per valore numerico
        uasort(
            $groups,
            static function (array $a, array $b): int {
                $lenA = strlen((string) ($a['value'] ?? ''));
                $lenB = strlen((string) ($b['value'] ?? ''));
                if ($lenA !== $lenB) {
                    return $lenA <=> $lenB;
                }

                $numA = (int) ($a['value'] ?? 0);
                $numB = (int) ($b['value'] ?? 0);

                return $numA <=> $numB;
            }
        );

        $usedCountries = [];
        $options = [];

        foreach ($groups as $group) {
            $countries = array_keys($group['countries']);
            sort($countries, SORT_NATURAL | SORT_FLAG_CASE);

            // Filtra i paesi già utilizzati da un altro gruppo
            $countries = array_values(array_filter(
                $countries,
                static function (string $name) use (&$usedCountries): bool {
                    if ($name === '') {
                        return false;
                    }
                    if (isset($usedCountries[$name])) {
                        return false;
                    }
                    $usedCountries[$name] = true;
                    return true;
                }
            ));

            if ($countries === []) {
                // Tutti i paesi di questo gruppo erano duplicati di altri prefissi
                continue;
            }

            $label = $group['prefix'];
            if ($countries !== []) {
                $label .= ' · ' . implode(', ', $countries);
            }

            $options[] = [
                'prefix'   => $group['prefix'],
                'value'    => $group['value'],
                'language' => $group['language'],
                'label'    => $label,
            ];
        }

        usort(
            $options,
            static function (array $first, array $second): int {
                $firstLabel  = isset($first['label']) ? (string) $first['label'] : '';
                $secondLabel = isset($second['label']) ? (string) $second['label'] : '';

                return strnatcasecmp($firstLabel, $secondLabel);
            }
        );

        return $options;
    }

    private function normalizePhonePrefix(string $prefix): string
    {
        $normalized = str_replace(' ', '', trim($prefix));
        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = '+' . substr($normalized, 2);
        } elseif (!str_starts_with($normalized, '+')) {
            $normalized = '+' . ltrim($normalized, '+');
        }

        $digits = preg_replace('/[^0-9]/', '', substr($normalized, 1));
        if (!is_string($digits) || $digits === '') {
            return '';
        }

        return '+' . $digits;
    }

    private function normalizePhoneLanguage(string $value): string
    {
        $upper = strtoupper(trim($value));
        if ($upper === '') {
            return 'INT';
        }

        if (str_starts_with($upper, 'IT')) {
            return 'IT';
        }

        if (str_starts_with($upper, 'EN')) {
            return 'EN';
        }

        if (str_starts_with($upper, 'INT')) {
            return 'INT';
        }

        return 'INT';
    }
}

