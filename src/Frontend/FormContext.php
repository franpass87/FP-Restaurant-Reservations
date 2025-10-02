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
use function sanitize_html_class;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
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
            'prefix' => '+1201',
            'value' => '1201',
            'language' => 'US',
            'label' => '+1201 · United States',
        ],
        [
            'prefix' => '+1202',
            'value' => '1202',
            'language' => 'US',
            'label' => '+1202 · United States',
        ],
        [
            'prefix' => '+1203',
            'value' => '1203',
            'language' => 'US',
            'label' => '+1203 · United States',
        ],
        [
            'prefix' => '+1204',
            'value' => '1204',
            'language' => 'CA',
            'label' => '+1204 · Canada',
        ],
        [
            'prefix' => '+1205',
            'value' => '1205',
            'language' => 'US',
            'label' => '+1205 · United States',
        ],
        [
            'prefix' => '+1206',
            'value' => '1206',
            'language' => 'US',
            'label' => '+1206 · United States',
        ],
        [
            'prefix' => '+1207',
            'value' => '1207',
            'language' => 'US',
            'label' => '+1207 · United States',
        ],
        [
            'prefix' => '+1208',
            'value' => '1208',
            'language' => 'US',
            'label' => '+1208 · United States',
        ],
        [
            'prefix' => '+1209',
            'value' => '1209',
            'language' => 'US',
            'label' => '+1209 · United States',
        ],
        [
            'prefix' => '+1210',
            'value' => '1210',
            'language' => 'US',
            'label' => '+1210 · United States',
        ],
        [
            'prefix' => '+1212',
            'value' => '1212',
            'language' => 'US',
            'label' => '+1212 · United States',
        ],
        [
            'prefix' => '+1213',
            'value' => '1213',
            'language' => 'US',
            'label' => '+1213 · United States',
        ],
        [
            'prefix' => '+1214',
            'value' => '1214',
            'language' => 'US',
            'label' => '+1214 · United States',
        ],
        [
            'prefix' => '+1215',
            'value' => '1215',
            'language' => 'US',
            'label' => '+1215 · United States',
        ],
        [
            'prefix' => '+1216',
            'value' => '1216',
            'language' => 'US',
            'label' => '+1216 · United States',
        ],
        [
            'prefix' => '+1217',
            'value' => '1217',
            'language' => 'US',
            'label' => '+1217 · United States',
        ],
        [
            'prefix' => '+1218',
            'value' => '1218',
            'language' => 'US',
            'label' => '+1218 · United States',
        ],
        [
            'prefix' => '+1219',
            'value' => '1219',
            'language' => 'US',
            'label' => '+1219 · United States',
        ],
        [
            'prefix' => '+1220',
            'value' => '1220',
            'language' => 'US',
            'label' => '+1220 · United States',
        ],
        [
            'prefix' => '+1223',
            'value' => '1223',
            'language' => 'US',
            'label' => '+1223 · United States',
        ],
        [
            'prefix' => '+1224',
            'value' => '1224',
            'language' => 'US',
            'label' => '+1224 · United States',
        ],
        [
            'prefix' => '+1225',
            'value' => '1225',
            'language' => 'US',
            'label' => '+1225 · United States',
        ],
        [
            'prefix' => '+1226',
            'value' => '1226',
            'language' => 'CA',
            'label' => '+1226 · Canada',
        ],
        [
            'prefix' => '+1227',
            'value' => '1227',
            'language' => 'US',
            'label' => '+1227 · United States',
        ],
        [
            'prefix' => '+1228',
            'value' => '1228',
            'language' => 'US',
            'label' => '+1228 · United States',
        ],
        [
            'prefix' => '+1229',
            'value' => '1229',
            'language' => 'US',
            'label' => '+1229 · United States',
        ],
        [
            'prefix' => '+1231',
            'value' => '1231',
            'language' => 'US',
            'label' => '+1231 · United States',
        ],
        [
            'prefix' => '+1234',
            'value' => '1234',
            'language' => 'US',
            'label' => '+1234 · United States',
        ],
        [
            'prefix' => '+1236',
            'value' => '1236',
            'language' => 'CA',
            'label' => '+1236 · Canada',
        ],
        [
            'prefix' => '+1239',
            'value' => '1239',
            'language' => 'US',
            'label' => '+1239 · United States',
        ],
        [
            'prefix' => '+1240',
            'value' => '1240',
            'language' => 'US',
            'label' => '+1240 · United States',
        ],
        [
            'prefix' => '+1242',
            'value' => '1242',
            'language' => 'BS',
            'label' => '+1242 · Bahamas',
        ],
        [
            'prefix' => '+1246',
            'value' => '1246',
            'language' => 'BB',
            'label' => '+1246 · Barbados',
        ],
        [
            'prefix' => '+1248',
            'value' => '1248',
            'language' => 'US',
            'label' => '+1248 · United States',
        ],
        [
            'prefix' => '+1249',
            'value' => '1249',
            'language' => 'CA',
            'label' => '+1249 · Canada',
        ],
        [
            'prefix' => '+1250',
            'value' => '1250',
            'language' => 'CA',
            'label' => '+1250 · Canada',
        ],
        [
            'prefix' => '+1251',
            'value' => '1251',
            'language' => 'US',
            'label' => '+1251 · United States',
        ],
        [
            'prefix' => '+1252',
            'value' => '1252',
            'language' => 'US',
            'label' => '+1252 · United States',
        ],
        [
            'prefix' => '+1253',
            'value' => '1253',
            'language' => 'US',
            'label' => '+1253 · United States',
        ],
        [
            'prefix' => '+1254',
            'value' => '1254',
            'language' => 'US',
            'label' => '+1254 · United States',
        ],
        [
            'prefix' => '+1256',
            'value' => '1256',
            'language' => 'US',
            'label' => '+1256 · United States',
        ],
        [
            'prefix' => '+1260',
            'value' => '1260',
            'language' => 'US',
            'label' => '+1260 · United States',
        ],
        [
            'prefix' => '+1262',
            'value' => '1262',
            'language' => 'US',
            'label' => '+1262 · United States',
        ],
        [
            'prefix' => '+1263',
            'value' => '1263',
            'language' => 'CA',
            'label' => '+1263 · Canada',
        ],
        [
            'prefix' => '+1264',
            'value' => '1264',
            'language' => 'AI',
            'label' => '+1264 · Anguilla',
        ],
        [
            'prefix' => '+1267',
            'value' => '1267',
            'language' => 'US',
            'label' => '+1267 · United States',
        ],
        [
            'prefix' => '+1268',
            'value' => '1268',
            'language' => 'AG',
            'label' => '+1268 · Antigua and Barbuda',
        ],
        [
            'prefix' => '+1269',
            'value' => '1269',
            'language' => 'US',
            'label' => '+1269 · United States',
        ],
        [
            'prefix' => '+1270',
            'value' => '1270',
            'language' => 'US',
            'label' => '+1270 · United States',
        ],
        [
            'prefix' => '+1272',
            'value' => '1272',
            'language' => 'US',
            'label' => '+1272 · United States',
        ],
        [
            'prefix' => '+1274',
            'value' => '1274',
            'language' => 'US',
            'label' => '+1274 · United States',
        ],
        [
            'prefix' => '+1276',
            'value' => '1276',
            'language' => 'US',
            'label' => '+1276 · United States',
        ],
        [
            'prefix' => '+1279',
            'value' => '1279',
            'language' => 'US',
            'label' => '+1279 · United States',
        ],
        [
            'prefix' => '+1281',
            'value' => '1281',
            'language' => 'US',
            'label' => '+1281 · United States',
        ],
        [
            'prefix' => '+1283',
            'value' => '1283',
            'language' => 'US',
            'label' => '+1283 · United States',
        ],
        [
            'prefix' => '+1284',
            'value' => '1284',
            'language' => 'VG',
            'label' => '+1284 · British Virgin Islands',
        ],
        [
            'prefix' => '+1289',
            'value' => '1289',
            'language' => 'CA',
            'label' => '+1289 · Canada',
        ],
        [
            'prefix' => '+1301',
            'value' => '1301',
            'language' => 'US',
            'label' => '+1301 · United States',
        ],
        [
            'prefix' => '+1302',
            'value' => '1302',
            'language' => 'US',
            'label' => '+1302 · United States',
        ],
        [
            'prefix' => '+1303',
            'value' => '1303',
            'language' => 'US',
            'label' => '+1303 · United States',
        ],
        [
            'prefix' => '+1304',
            'value' => '1304',
            'language' => 'US',
            'label' => '+1304 · United States',
        ],
        [
            'prefix' => '+1305',
            'value' => '1305',
            'language' => 'US',
            'label' => '+1305 · United States',
        ],
        [
            'prefix' => '+1306',
            'value' => '1306',
            'language' => 'CA',
            'label' => '+1306 · Canada',
        ],
        [
            'prefix' => '+1307',
            'value' => '1307',
            'language' => 'US',
            'label' => '+1307 · United States',
        ],
        [
            'prefix' => '+1308',
            'value' => '1308',
            'language' => 'US',
            'label' => '+1308 · United States',
        ],
        [
            'prefix' => '+1309',
            'value' => '1309',
            'language' => 'US',
            'label' => '+1309 · United States',
        ],
        [
            'prefix' => '+1310',
            'value' => '1310',
            'language' => 'US',
            'label' => '+1310 · United States',
        ],
        [
            'prefix' => '+1312',
            'value' => '1312',
            'language' => 'US',
            'label' => '+1312 · United States',
        ],
        [
            'prefix' => '+1313',
            'value' => '1313',
            'language' => 'US',
            'label' => '+1313 · United States',
        ],
        [
            'prefix' => '+1314',
            'value' => '1314',
            'language' => 'US',
            'label' => '+1314 · United States',
        ],
        [
            'prefix' => '+1315',
            'value' => '1315',
            'language' => 'US',
            'label' => '+1315 · United States',
        ],
        [
            'prefix' => '+1316',
            'value' => '1316',
            'language' => 'US',
            'label' => '+1316 · United States',
        ],
        [
            'prefix' => '+1317',
            'value' => '1317',
            'language' => 'US',
            'label' => '+1317 · United States',
        ],
        [
            'prefix' => '+1318',
            'value' => '1318',
            'language' => 'US',
            'label' => '+1318 · United States',
        ],
        [
            'prefix' => '+1319',
            'value' => '1319',
            'language' => 'US',
            'label' => '+1319 · United States',
        ],
        [
            'prefix' => '+1320',
            'value' => '1320',
            'language' => 'US',
            'label' => '+1320 · United States',
        ],
        [
            'prefix' => '+1321',
            'value' => '1321',
            'language' => 'US',
            'label' => '+1321 · United States',
        ],
        [
            'prefix' => '+1323',
            'value' => '1323',
            'language' => 'US',
            'label' => '+1323 · United States',
        ],
        [
            'prefix' => '+1325',
            'value' => '1325',
            'language' => 'US',
            'label' => '+1325 · United States',
        ],
        [
            'prefix' => '+1326',
            'value' => '1326',
            'language' => 'US',
            'label' => '+1326 · United States',
        ],
        [
            'prefix' => '+1327',
            'value' => '1327',
            'language' => 'US',
            'label' => '+1327 · United States',
        ],
        [
            'prefix' => '+1330',
            'value' => '1330',
            'language' => 'US',
            'label' => '+1330 · United States',
        ],
        [
            'prefix' => '+1331',
            'value' => '1331',
            'language' => 'US',
            'label' => '+1331 · United States',
        ],
        [
            'prefix' => '+1332',
            'value' => '1332',
            'language' => 'US',
            'label' => '+1332 · United States',
        ],
        [
            'prefix' => '+1334',
            'value' => '1334',
            'language' => 'US',
            'label' => '+1334 · United States',
        ],
        [
            'prefix' => '+1336',
            'value' => '1336',
            'language' => 'US',
            'label' => '+1336 · United States',
        ],
        [
            'prefix' => '+1337',
            'value' => '1337',
            'language' => 'US',
            'label' => '+1337 · United States',
        ],
        [
            'prefix' => '+1339',
            'value' => '1339',
            'language' => 'US',
            'label' => '+1339 · United States',
        ],
        [
            'prefix' => '+1340',
            'value' => '1340',
            'language' => 'VI',
            'label' => '+1340 · United States Virgin Islands',
        ],
        [
            'prefix' => '+1341',
            'value' => '1341',
            'language' => 'US',
            'label' => '+1341 · United States',
        ],
        [
            'prefix' => '+1343',
            'value' => '1343',
            'language' => 'CA',
            'label' => '+1343 · Canada',
        ],
        [
            'prefix' => '+1345',
            'value' => '1345',
            'language' => 'KY',
            'label' => '+1345 · Cayman Islands',
        ],
        [
            'prefix' => '+1346',
            'value' => '1346',
            'language' => 'US',
            'label' => '+1346 · United States',
        ],
        [
            'prefix' => '+1347',
            'value' => '1347',
            'language' => 'US',
            'label' => '+1347 · United States',
        ],
        [
            'prefix' => '+1351',
            'value' => '1351',
            'language' => 'US',
            'label' => '+1351 · United States',
        ],
        [
            'prefix' => '+1352',
            'value' => '1352',
            'language' => 'US',
            'label' => '+1352 · United States',
        ],
        [
            'prefix' => '+1354',
            'value' => '1354',
            'language' => 'CA',
            'label' => '+1354 · Canada',
        ],
        [
            'prefix' => '+1360',
            'value' => '1360',
            'language' => 'US',
            'label' => '+1360 · United States',
        ],
        [
            'prefix' => '+1361',
            'value' => '1361',
            'language' => 'US',
            'label' => '+1361 · United States',
        ],
        [
            'prefix' => '+1364',
            'value' => '1364',
            'language' => 'US',
            'label' => '+1364 · United States',
        ],
        [
            'prefix' => '+1365',
            'value' => '1365',
            'language' => 'CA',
            'label' => '+1365 · Canada',
        ],
        [
            'prefix' => '+1367',
            'value' => '1367',
            'language' => 'CA',
            'label' => '+1367 · Canada',
        ],
        [
            'prefix' => '+1368',
            'value' => '1368',
            'language' => 'CA',
            'label' => '+1368 · Canada',
        ],
        [
            'prefix' => '+1380',
            'value' => '1380',
            'language' => 'US',
            'label' => '+1380 · United States',
        ],
        [
            'prefix' => '+1382',
            'value' => '1382',
            'language' => 'CA',
            'label' => '+1382 · Canada',
        ],
        [
            'prefix' => '+1385',
            'value' => '1385',
            'language' => 'US',
            'label' => '+1385 · United States',
        ],
        [
            'prefix' => '+1386',
            'value' => '1386',
            'language' => 'US',
            'label' => '+1386 · United States',
        ],
        [
            'prefix' => '+1387',
            'value' => '1387',
            'language' => 'CA',
            'label' => '+1387 · Canada',
        ],
        [
            'prefix' => '+1401',
            'value' => '1401',
            'language' => 'US',
            'label' => '+1401 · United States',
        ],
        [
            'prefix' => '+1402',
            'value' => '1402',
            'language' => 'US',
            'label' => '+1402 · United States',
        ],
        [
            'prefix' => '+1403',
            'value' => '1403',
            'language' => 'CA',
            'label' => '+1403 · Canada',
        ],
        [
            'prefix' => '+1404',
            'value' => '1404',
            'language' => 'US',
            'label' => '+1404 · United States',
        ],
        [
            'prefix' => '+1405',
            'value' => '1405',
            'language' => 'US',
            'label' => '+1405 · United States',
        ],
        [
            'prefix' => '+1406',
            'value' => '1406',
            'language' => 'US',
            'label' => '+1406 · United States',
        ],
        [
            'prefix' => '+1407',
            'value' => '1407',
            'language' => 'US',
            'label' => '+1407 · United States',
        ],
        [
            'prefix' => '+1408',
            'value' => '1408',
            'language' => 'US',
            'label' => '+1408 · United States',
        ],
        [
            'prefix' => '+1409',
            'value' => '1409',
            'language' => 'US',
            'label' => '+1409 · United States',
        ],
        [
            'prefix' => '+1410',
            'value' => '1410',
            'language' => 'US',
            'label' => '+1410 · United States',
        ],
        [
            'prefix' => '+1412',
            'value' => '1412',
            'language' => 'US',
            'label' => '+1412 · United States',
        ],
        [
            'prefix' => '+1413',
            'value' => '1413',
            'language' => 'US',
            'label' => '+1413 · United States',
        ],
        [
            'prefix' => '+1414',
            'value' => '1414',
            'language' => 'US',
            'label' => '+1414 · United States',
        ],
        [
            'prefix' => '+1415',
            'value' => '1415',
            'language' => 'US',
            'label' => '+1415 · United States',
        ],
        [
            'prefix' => '+1416',
            'value' => '1416',
            'language' => 'CA',
            'label' => '+1416 · Canada',
        ],
        [
            'prefix' => '+1417',
            'value' => '1417',
            'language' => 'US',
            'label' => '+1417 · United States',
        ],
        [
            'prefix' => '+1418',
            'value' => '1418',
            'language' => 'CA',
            'label' => '+1418 · Canada',
        ],
        [
            'prefix' => '+1419',
            'value' => '1419',
            'language' => 'US',
            'label' => '+1419 · United States',
        ],
        [
            'prefix' => '+1423',
            'value' => '1423',
            'language' => 'US',
            'label' => '+1423 · United States',
        ],
        [
            'prefix' => '+1424',
            'value' => '1424',
            'language' => 'US',
            'label' => '+1424 · United States',
        ],
        [
            'prefix' => '+1425',
            'value' => '1425',
            'language' => 'US',
            'label' => '+1425 · United States',
        ],
        [
            'prefix' => '+1428',
            'value' => '1428',
            'language' => 'CA',
            'label' => '+1428 · Canada',
        ],
        [
            'prefix' => '+1430',
            'value' => '1430',
            'language' => 'US',
            'label' => '+1430 · United States',
        ],
        [
            'prefix' => '+1431',
            'value' => '1431',
            'language' => 'CA',
            'label' => '+1431 · Canada',
        ],
        [
            'prefix' => '+1432',
            'value' => '1432',
            'language' => 'US',
            'label' => '+1432 · United States',
        ],
        [
            'prefix' => '+1434',
            'value' => '1434',
            'language' => 'US',
            'label' => '+1434 · United States',
        ],
        [
            'prefix' => '+1435',
            'value' => '1435',
            'language' => 'US',
            'label' => '+1435 · United States',
        ],
        [
            'prefix' => '+1437',
            'value' => '1437',
            'language' => 'CA',
            'label' => '+1437 · Canada',
        ],
        [
            'prefix' => '+1438',
            'value' => '1438',
            'language' => 'CA',
            'label' => '+1438 · Canada',
        ],
        [
            'prefix' => '+1440',
            'value' => '1440',
            'language' => 'US',
            'label' => '+1440 · United States',
        ],
        [
            'prefix' => '+1441',
            'value' => '1441',
            'language' => 'BM',
            'label' => '+1441 · Bermuda',
        ],
        [
            'prefix' => '+1442',
            'value' => '1442',
            'language' => 'US',
            'label' => '+1442 · United States',
        ],
        [
            'prefix' => '+1443',
            'value' => '1443',
            'language' => 'US',
            'label' => '+1443 · United States',
        ],
        [
            'prefix' => '+1445',
            'value' => '1445',
            'language' => 'US',
            'label' => '+1445 · United States',
        ],
        [
            'prefix' => '+1447',
            'value' => '1447',
            'language' => 'US',
            'label' => '+1447 · United States',
        ],
        [
            'prefix' => '+1448',
            'value' => '1448',
            'language' => 'US',
            'label' => '+1448 · United States',
        ],
        [
            'prefix' => '+1450',
            'value' => '1450',
            'language' => 'CA',
            'label' => '+1450 · Canada',
        ],
        [
            'prefix' => '+1458',
            'value' => '1458',
            'language' => 'US',
            'label' => '+1458 · United States',
        ],
        [
            'prefix' => '+1463',
            'value' => '1463',
            'language' => 'US',
            'label' => '+1463 · United States',
        ],
        [
            'prefix' => '+1464',
            'value' => '1464',
            'language' => 'US',
            'label' => '+1464 · United States',
        ],
        [
            'prefix' => '+1468',
            'value' => '1468',
            'language' => 'CA',
            'label' => '+1468 · Canada',
        ],
        [
            'prefix' => '+1469',
            'value' => '1469',
            'language' => 'US',
            'label' => '+1469 · United States',
        ],
        [
            'prefix' => '+1470',
            'value' => '1470',
            'language' => 'US',
            'label' => '+1470 · United States',
        ],
        [
            'prefix' => '+1473',
            'value' => '1473',
            'language' => 'GD',
            'label' => '+1473 · Grenada',
        ],
        [
            'prefix' => '+1474',
            'value' => '1474',
            'language' => 'CA',
            'label' => '+1474 · Canada',
        ],
        [
            'prefix' => '+1475',
            'value' => '1475',
            'language' => 'US',
            'label' => '+1475 · United States',
        ],
        [
            'prefix' => '+1478',
            'value' => '1478',
            'language' => 'US',
            'label' => '+1478 · United States',
        ],
        [
            'prefix' => '+1479',
            'value' => '1479',
            'language' => 'US',
            'label' => '+1479 · United States',
        ],
        [
            'prefix' => '+1480',
            'value' => '1480',
            'language' => 'US',
            'label' => '+1480 · United States',
        ],
        [
            'prefix' => '+1484',
            'value' => '1484',
            'language' => 'US',
            'label' => '+1484 · United States',
        ],
        [
            'prefix' => '+1500',
            'value' => '1500',
            'language' => 'US',
            'label' => '+1500 · United States',
        ],
        [
            'prefix' => '+1501',
            'value' => '1501',
            'language' => 'US',
            'label' => '+1501 · United States',
        ],
        [
            'prefix' => '+1502',
            'value' => '1502',
            'language' => 'US',
            'label' => '+1502 · United States',
        ],
        [
            'prefix' => '+1503',
            'value' => '1503',
            'language' => 'US',
            'label' => '+1503 · United States',
        ],
        [
            'prefix' => '+1504',
            'value' => '1504',
            'language' => 'US',
            'label' => '+1504 · United States',
        ],
        [
            'prefix' => '+1505',
            'value' => '1505',
            'language' => 'US',
            'label' => '+1505 · United States',
        ],
        [
            'prefix' => '+1506',
            'value' => '1506',
            'language' => 'CA',
            'label' => '+1506 · Canada',
        ],
        [
            'prefix' => '+1507',
            'value' => '1507',
            'language' => 'US',
            'label' => '+1507 · United States',
        ],
        [
            'prefix' => '+1508',
            'value' => '1508',
            'language' => 'US',
            'label' => '+1508 · United States',
        ],
        [
            'prefix' => '+1509',
            'value' => '1509',
            'language' => 'US',
            'label' => '+1509 · United States',
        ],
        [
            'prefix' => '+1510',
            'value' => '1510',
            'language' => 'US',
            'label' => '+1510 · United States',
        ],
        [
            'prefix' => '+1512',
            'value' => '1512',
            'language' => 'US',
            'label' => '+1512 · United States',
        ],
        [
            'prefix' => '+1513',
            'value' => '1513',
            'language' => 'US',
            'label' => '+1513 · United States',
        ],
        [
            'prefix' => '+1514',
            'value' => '1514',
            'language' => 'CA',
            'label' => '+1514 · Canada',
        ],
        [
            'prefix' => '+1515',
            'value' => '1515',
            'language' => 'US',
            'label' => '+1515 · United States',
        ],
        [
            'prefix' => '+1516',
            'value' => '1516',
            'language' => 'US',
            'label' => '+1516 · United States',
        ],
        [
            'prefix' => '+1517',
            'value' => '1517',
            'language' => 'US',
            'label' => '+1517 · United States',
        ],
        [
            'prefix' => '+1518',
            'value' => '1518',
            'language' => 'US',
            'label' => '+1518 · United States',
        ],
        [
            'prefix' => '+1519',
            'value' => '1519',
            'language' => 'CA',
            'label' => '+1519 · Canada',
        ],
        [
            'prefix' => '+1520',
            'value' => '1520',
            'language' => 'US',
            'label' => '+1520 · United States',
        ],
        [
            'prefix' => '+1521',
            'value' => '1521',
            'language' => 'US',
            'label' => '+1521 · United States',
        ],
        [
            'prefix' => '+1522',
            'value' => '1522',
            'language' => 'US',
            'label' => '+1522 · United States',
        ],
        [
            'prefix' => '+1523',
            'value' => '1523',
            'language' => 'US',
            'label' => '+1523 · United States',
        ],
        [
            'prefix' => '+1524',
            'value' => '1524',
            'language' => 'US',
            'label' => '+1524 · United States',
        ],
        [
            'prefix' => '+1525',
            'value' => '1525',
            'language' => 'US',
            'label' => '+1525 · United States',
        ],
        [
            'prefix' => '+1526',
            'value' => '1526',
            'language' => 'US',
            'label' => '+1526 · United States',
        ],
        [
            'prefix' => '+1527',
            'value' => '1527',
            'language' => 'US',
            'label' => '+1527 · United States',
        ],
        [
            'prefix' => '+1528',
            'value' => '1528',
            'language' => 'US',
            'label' => '+1528 · United States',
        ],
        [
            'prefix' => '+1529',
            'value' => '1529',
            'language' => 'US',
            'label' => '+1529 · United States',
        ],
        [
            'prefix' => '+1530',
            'value' => '1530',
            'language' => 'US',
            'label' => '+1530 · United States',
        ],
        [
            'prefix' => '+1531',
            'value' => '1531',
            'language' => 'US',
            'label' => '+1531 · United States',
        ],
        [
            'prefix' => '+1532',
            'value' => '1532',
            'language' => 'US',
            'label' => '+1532 · United States',
        ],
        [
            'prefix' => '+1533',
            'value' => '1533',
            'language' => 'US',
            'label' => '+1533 · United States',
        ],
        [
            'prefix' => '+1534',
            'value' => '1534',
            'language' => 'US',
            'label' => '+1534 · United States',
        ],
        [
            'prefix' => '+1535',
            'value' => '1535',
            'language' => 'US',
            'label' => '+1535 · United States',
        ],
        [
            'prefix' => '+1538',
            'value' => '1538',
            'language' => 'US',
            'label' => '+1538 · United States',
        ],
        [
            'prefix' => '+1539',
            'value' => '1539',
            'language' => 'US',
            'label' => '+1539 · United States',
        ],
        [
            'prefix' => '+1540',
            'value' => '1540',
            'language' => 'US',
            'label' => '+1540 · United States',
        ],
        [
            'prefix' => '+1541',
            'value' => '1541',
            'language' => 'US',
            'label' => '+1541 · United States',
        ],
        [
            'prefix' => '+1542',
            'value' => '1542',
            'language' => 'US',
            'label' => '+1542 · United States',
        ],
        [
            'prefix' => '+1543',
            'value' => '1543',
            'language' => 'US',
            'label' => '+1543 · United States',
        ],
        [
            'prefix' => '+1544',
            'value' => '1544',
            'language' => 'US',
            'label' => '+1544 · United States',
        ],
        [
            'prefix' => '+1545',
            'value' => '1545',
            'language' => 'US',
            'label' => '+1545 · United States',
        ],
        [
            'prefix' => '+1546',
            'value' => '1546',
            'language' => 'US',
            'label' => '+1546 · United States',
        ],
        [
            'prefix' => '+1547',
            'value' => '1547',
            'language' => 'US',
            'label' => '+1547 · United States',
        ],
        [
            'prefix' => '+1548',
            'value' => '1548',
            'language' => 'CA',
            'label' => '+1548 · Canada',
        ],
        [
            'prefix' => '+1549',
            'value' => '1549',
            'language' => 'US',
            'label' => '+1549 · United States',
        ],
        [
            'prefix' => '+1550',
            'value' => '1550',
            'language' => 'US',
            'label' => '+1550 · United States',
        ],
        [
            'prefix' => '+1551',
            'value' => '1551',
            'language' => 'US',
            'label' => '+1551 · United States',
        ],
        [
            'prefix' => '+1552',
            'value' => '1552',
            'language' => 'US',
            'label' => '+1552 · United States',
        ],
        [
            'prefix' => '+1553',
            'value' => '1553',
            'language' => 'US',
            'label' => '+1553 · United States',
        ],
        [
            'prefix' => '+1554',
            'value' => '1554',
            'language' => 'US',
            'label' => '+1554 · United States',
        ],
        [
            'prefix' => '+1556',
            'value' => '1556',
            'language' => 'US',
            'label' => '+1556 · United States',
        ],
        [
            'prefix' => '+1557',
            'value' => '1557',
            'language' => 'US',
            'label' => '+1557 · United States',
        ],
        [
            'prefix' => '+1558',
            'value' => '1558',
            'language' => 'US',
            'label' => '+1558 · United States',
        ],
        [
            'prefix' => '+1559',
            'value' => '1559',
            'language' => 'US',
            'label' => '+1559 · United States',
        ],
        [
            'prefix' => '+1561',
            'value' => '1561',
            'language' => 'US',
            'label' => '+1561 · United States',
        ],
        [
            'prefix' => '+1562',
            'value' => '1562',
            'language' => 'US',
            'label' => '+1562 · United States',
        ],
        [
            'prefix' => '+1563',
            'value' => '1563',
            'language' => 'US',
            'label' => '+1563 · United States',
        ],
        [
            'prefix' => '+1564',
            'value' => '1564',
            'language' => 'US',
            'label' => '+1564 · United States',
        ],
        [
            'prefix' => '+1566',
            'value' => '1566',
            'language' => 'US',
            'label' => '+1566 · United States',
        ],
        [
            'prefix' => '+1567',
            'value' => '1567',
            'language' => 'US',
            'label' => '+1567 · United States',
        ],
        [
            'prefix' => '+1569',
            'value' => '1569',
            'language' => 'US',
            'label' => '+1569 · United States',
        ],
        [
            'prefix' => '+1570',
            'value' => '1570',
            'language' => 'US',
            'label' => '+1570 · United States',
        ],
        [
            'prefix' => '+1571',
            'value' => '1571',
            'language' => 'US',
            'label' => '+1571 · United States',
        ],
        [
            'prefix' => '+1572',
            'value' => '1572',
            'language' => 'US',
            'label' => '+1572 · United States',
        ],
        [
            'prefix' => '+1573',
            'value' => '1573',
            'language' => 'US',
            'label' => '+1573 · United States',
        ],
        [
            'prefix' => '+1574',
            'value' => '1574',
            'language' => 'US',
            'label' => '+1574 · United States',
        ],
        [
            'prefix' => '+1575',
            'value' => '1575',
            'language' => 'US',
            'label' => '+1575 · United States',
        ],
        [
            'prefix' => '+1577',
            'value' => '1577',
            'language' => 'US',
            'label' => '+1577 · United States',
        ],
        [
            'prefix' => '+1578',
            'value' => '1578',
            'language' => 'US',
            'label' => '+1578 · United States',
        ],
        [
            'prefix' => '+1579',
            'value' => '1579',
            'language' => 'CA',
            'label' => '+1579 · Canada',
        ],
        [
            'prefix' => '+1580',
            'value' => '1580',
            'language' => 'US',
            'label' => '+1580 · United States',
        ],
        [
            'prefix' => '+1581',
            'value' => '1581',
            'language' => 'CA',
            'label' => '+1581 · Canada',
        ],
        [
            'prefix' => '+1582',
            'value' => '1582',
            'language' => 'US',
            'label' => '+1582 · United States',
        ],
        [
            'prefix' => '+1584',
            'value' => '1584',
            'language' => 'CA',
            'label' => '+1584 · Canada',
        ],
        [
            'prefix' => '+1585',
            'value' => '1585',
            'language' => 'US',
            'label' => '+1585 · United States',
        ],
        [
            'prefix' => '+1586',
            'value' => '1586',
            'language' => 'US',
            'label' => '+1586 · United States',
        ],
        [
            'prefix' => '+1587',
            'value' => '1587',
            'language' => 'CA',
            'label' => '+1587 · Canada',
        ],
        [
            'prefix' => '+1588',
            'value' => '1588',
            'language' => 'US',
            'label' => '+1588 · United States',
        ],
        [
            'prefix' => '+1589',
            'value' => '1589',
            'language' => 'US',
            'label' => '+1589 · United States',
        ],
        [
            'prefix' => '+1600',
            'value' => '1600',
            'language' => 'CA',
            'label' => '+1600 · Canada',
        ],
        [
            'prefix' => '+1601',
            'value' => '1601',
            'language' => 'US',
            'label' => '+1601 · United States',
        ],
        [
            'prefix' => '+1602',
            'value' => '1602',
            'language' => 'US',
            'label' => '+1602 · United States',
        ],
        [
            'prefix' => '+1603',
            'value' => '1603',
            'language' => 'US',
            'label' => '+1603 · United States',
        ],
        [
            'prefix' => '+1604',
            'value' => '1604',
            'language' => 'CA',
            'label' => '+1604 · Canada',
        ],
        [
            'prefix' => '+1605',
            'value' => '1605',
            'language' => 'US',
            'label' => '+1605 · United States',
        ],
        [
            'prefix' => '+1606',
            'value' => '1606',
            'language' => 'US',
            'label' => '+1606 · United States',
        ],
        [
            'prefix' => '+1607',
            'value' => '1607',
            'language' => 'US',
            'label' => '+1607 · United States',
        ],
        [
            'prefix' => '+1608',
            'value' => '1608',
            'language' => 'US',
            'label' => '+1608 · United States',
        ],
        [
            'prefix' => '+1609',
            'value' => '1609',
            'language' => 'US',
            'label' => '+1609 · United States',
        ],
        [
            'prefix' => '+1610',
            'value' => '1610',
            'language' => 'US',
            'label' => '+1610 · United States',
        ],
        [
            'prefix' => '+1612',
            'value' => '1612',
            'language' => 'US',
            'label' => '+1612 · United States',
        ],
        [
            'prefix' => '+1613',
            'value' => '1613',
            'language' => 'CA',
            'label' => '+1613 · Canada',
        ],
        [
            'prefix' => '+1614',
            'value' => '1614',
            'language' => 'US',
            'label' => '+1614 · United States',
        ],
        [
            'prefix' => '+1615',
            'value' => '1615',
            'language' => 'US',
            'label' => '+1615 · United States',
        ],
        [
            'prefix' => '+1616',
            'value' => '1616',
            'language' => 'US',
            'label' => '+1616 · United States',
        ],
        [
            'prefix' => '+1617',
            'value' => '1617',
            'language' => 'US',
            'label' => '+1617 · United States',
        ],
        [
            'prefix' => '+1618',
            'value' => '1618',
            'language' => 'US',
            'label' => '+1618 · United States',
        ],
        [
            'prefix' => '+1619',
            'value' => '1619',
            'language' => 'US',
            'label' => '+1619 · United States',
        ],
        [
            'prefix' => '+1620',
            'value' => '1620',
            'language' => 'US',
            'label' => '+1620 · United States',
        ],
        [
            'prefix' => '+1622',
            'value' => '1622',
            'language' => 'CA',
            'label' => '+1622 · Canada',
        ],
        [
            'prefix' => '+1623',
            'value' => '1623',
            'language' => 'US',
            'label' => '+1623 · United States',
        ],
        [
            'prefix' => '+1626',
            'value' => '1626',
            'language' => 'US',
            'label' => '+1626 · United States',
        ],
        [
            'prefix' => '+1628',
            'value' => '1628',
            'language' => 'US',
            'label' => '+1628 · United States',
        ],
        [
            'prefix' => '+1629',
            'value' => '1629',
            'language' => 'US',
            'label' => '+1629 · United States',
        ],
        [
            'prefix' => '+1630',
            'value' => '1630',
            'language' => 'US',
            'label' => '+1630 · United States',
        ],
        [
            'prefix' => '+1631',
            'value' => '1631',
            'language' => 'US',
            'label' => '+1631 · United States',
        ],
        [
            'prefix' => '+1633',
            'value' => '1633',
            'language' => 'CA',
            'label' => '+1633 · Canada',
        ],
        [
            'prefix' => '+1636',
            'value' => '1636',
            'language' => 'US',
            'label' => '+1636 · United States',
        ],
        [
            'prefix' => '+1639',
            'value' => '1639',
            'language' => 'CA',
            'label' => '+1639 · Canada',
        ],
        [
            'prefix' => '+1640',
            'value' => '1640',
            'language' => 'US',
            'label' => '+1640 · United States',
        ],
        [
            'prefix' => '+1641',
            'value' => '1641',
            'language' => 'US',
            'label' => '+1641 · United States',
        ],
        [
            'prefix' => '+1644',
            'value' => '1644',
            'language' => 'CA',
            'label' => '+1644 · Canada',
        ],
        [
            'prefix' => '+1646',
            'value' => '1646',
            'language' => 'US',
            'label' => '+1646 · United States',
        ],
        [
            'prefix' => '+1647',
            'value' => '1647',
            'language' => 'CA',
            'label' => '+1647 · Canada',
        ],
        [
            'prefix' => '+1649',
            'value' => '1649',
            'language' => 'TC',
            'label' => '+1649 · Turks and Caicos Islands',
        ],
        [
            'prefix' => '+1650',
            'value' => '1650',
            'language' => 'US',
            'label' => '+1650 · United States',
        ],
        [
            'prefix' => '+1651',
            'value' => '1651',
            'language' => 'US',
            'label' => '+1651 · United States',
        ],
        [
            'prefix' => '+1655',
            'value' => '1655',
            'language' => 'CA',
            'label' => '+1655 · Canada',
        ],
        [
            'prefix' => '+1656',
            'value' => '1656',
            'language' => 'US',
            'label' => '+1656 · United States',
        ],
        [
            'prefix' => '+1657',
            'value' => '1657',
            'language' => 'US',
            'label' => '+1657 · United States',
        ],
        [
            'prefix' => '+1659',
            'value' => '1659',
            'language' => 'US',
            'label' => '+1659 · United States',
        ],
        [
            'prefix' => '+1660',
            'value' => '1660',
            'language' => 'US',
            'label' => '+1660 · United States',
        ],
        [
            'prefix' => '+1661',
            'value' => '1661',
            'language' => 'US',
            'label' => '+1661 · United States',
        ],
        [
            'prefix' => '+1662',
            'value' => '1662',
            'language' => 'US',
            'label' => '+1662 · United States',
        ],
        [
            'prefix' => '+1664',
            'value' => '1664',
            'language' => 'MS',
            'label' => '+1664 · Montserrat',
        ],
        [
            'prefix' => '+1667',
            'value' => '1667',
            'language' => 'US',
            'label' => '+1667 · United States',
        ],
        [
            'prefix' => '+1669',
            'value' => '1669',
            'language' => 'US',
            'label' => '+1669 · United States',
        ],
        [
            'prefix' => '+1670',
            'value' => '1670',
            'language' => 'MP',
            'label' => '+1670 · Northern Mariana Islands',
        ],
        [
            'prefix' => '+1671',
            'value' => '1671',
            'language' => 'GU',
            'label' => '+1671 · Guam',
        ],
        [
            'prefix' => '+1672',
            'value' => '1672',
            'language' => 'CA',
            'label' => '+1672 · Canada',
        ],
        [
            'prefix' => '+1677',
            'value' => '1677',
            'language' => 'CA',
            'label' => '+1677 · Canada',
        ],
        [
            'prefix' => '+1678',
            'value' => '1678',
            'language' => 'US',
            'label' => '+1678 · United States',
        ],
        [
            'prefix' => '+1679',
            'value' => '1679',
            'language' => 'US',
            'label' => '+1679 · United States',
        ],
        [
            'prefix' => '+1680',
            'value' => '1680',
            'language' => 'US',
            'label' => '+1680 · United States',
        ],
        [
            'prefix' => '+1681',
            'value' => '1681',
            'language' => 'US',
            'label' => '+1681 · United States',
        ],
        [
            'prefix' => '+1682',
            'value' => '1682',
            'language' => 'US',
            'label' => '+1682 · United States',
        ],
        [
            'prefix' => '+1683',
            'value' => '1683',
            'language' => 'CA',
            'label' => '+1683 · Canada',
        ],
        [
            'prefix' => '+1684',
            'value' => '1684',
            'language' => 'AS',
            'label' => '+1684 · American Samoa',
        ],
        [
            'prefix' => '+1688',
            'value' => '1688',
            'language' => 'CA',
            'label' => '+1688 · Canada',
        ],
        [
            'prefix' => '+1689',
            'value' => '1689',
            'language' => 'US',
            'label' => '+1689 · United States',
        ],
        [
            'prefix' => '+1700',
            'value' => '1700',
            'language' => 'US',
            'label' => '+1700 · United States',
        ],
        [
            'prefix' => '+1701',
            'value' => '1701',
            'language' => 'US',
            'label' => '+1701 · United States',
        ],
        [
            'prefix' => '+1702',
            'value' => '1702',
            'language' => 'US',
            'label' => '+1702 · United States',
        ],
        [
            'prefix' => '+1703',
            'value' => '1703',
            'language' => 'US',
            'label' => '+1703 · United States',
        ],
        [
            'prefix' => '+1704',
            'value' => '1704',
            'language' => 'US',
            'label' => '+1704 · United States',
        ],
        [
            'prefix' => '+1705',
            'value' => '1705',
            'language' => 'CA',
            'label' => '+1705 · Canada',
        ],
        [
            'prefix' => '+1706',
            'value' => '1706',
            'language' => 'US',
            'label' => '+1706 · United States',
        ],
        [
            'prefix' => '+1707',
            'value' => '1707',
            'language' => 'US',
            'label' => '+1707 · United States',
        ],
        [
            'prefix' => '+1708',
            'value' => '1708',
            'language' => 'US',
            'label' => '+1708 · United States',
        ],
        [
            'prefix' => '+1709',
            'value' => '1709',
            'language' => 'CA',
            'label' => '+1709 · Canada',
        ],
        [
            'prefix' => '+1710',
            'value' => '1710',
            'language' => 'US',
            'label' => '+1710 · United States',
        ],
        [
            'prefix' => '+1712',
            'value' => '1712',
            'language' => 'US',
            'label' => '+1712 · United States',
        ],
        [
            'prefix' => '+1713',
            'value' => '1713',
            'language' => 'US',
            'label' => '+1713 · United States',
        ],
        [
            'prefix' => '+1714',
            'value' => '1714',
            'language' => 'US',
            'label' => '+1714 · United States',
        ],
        [
            'prefix' => '+1715',
            'value' => '1715',
            'language' => 'US',
            'label' => '+1715 · United States',
        ],
        [
            'prefix' => '+1716',
            'value' => '1716',
            'language' => 'US',
            'label' => '+1716 · United States',
        ],
        [
            'prefix' => '+1717',
            'value' => '1717',
            'language' => 'US',
            'label' => '+1717 · United States',
        ],
        [
            'prefix' => '+1718',
            'value' => '1718',
            'language' => 'US',
            'label' => '+1718 · United States',
        ],
        [
            'prefix' => '+1719',
            'value' => '1719',
            'language' => 'US',
            'label' => '+1719 · United States',
        ],
        [
            'prefix' => '+1720',
            'value' => '1720',
            'language' => 'US',
            'label' => '+1720 · United States',
        ],
        [
            'prefix' => '+1721',
            'value' => '1721',
            'language' => 'SX',
            'label' => '+1721 · Sint Maarten',
        ],
        [
            'prefix' => '+1724',
            'value' => '1724',
            'language' => 'US',
            'label' => '+1724 · United States',
        ],
        [
            'prefix' => '+1725',
            'value' => '1725',
            'language' => 'US',
            'label' => '+1725 · United States',
        ],
        [
            'prefix' => '+1726',
            'value' => '1726',
            'language' => 'US',
            'label' => '+1726 · United States',
        ],
        [
            'prefix' => '+1727',
            'value' => '1727',
            'language' => 'US',
            'label' => '+1727 · United States',
        ],
        [
            'prefix' => '+1730',
            'value' => '1730',
            'language' => 'US',
            'label' => '+1730 · United States',
        ],
        [
            'prefix' => '+1731',
            'value' => '1731',
            'language' => 'US',
            'label' => '+1731 · United States',
        ],
        [
            'prefix' => '+1732',
            'value' => '1732',
            'language' => 'US',
            'label' => '+1732 · United States',
        ],
        [
            'prefix' => '+1734',
            'value' => '1734',
            'language' => 'US',
            'label' => '+1734 · United States',
        ],
        [
            'prefix' => '+1737',
            'value' => '1737',
            'language' => 'US',
            'label' => '+1737 · United States',
        ],
        [
            'prefix' => '+1740',
            'value' => '1740',
            'language' => 'US',
            'label' => '+1740 · United States',
        ],
        [
            'prefix' => '+1742',
            'value' => '1742',
            'language' => 'CA',
            'label' => '+1742 · Canada',
        ],
        [
            'prefix' => '+1743',
            'value' => '1743',
            'language' => 'US',
            'label' => '+1743 · United States',
        ],
        [
            'prefix' => '+1747',
            'value' => '1747',
            'language' => 'US',
            'label' => '+1747 · United States',
        ],
        [
            'prefix' => '+1753',
            'value' => '1753',
            'language' => 'CA',
            'label' => '+1753 · Canada',
        ],
        [
            'prefix' => '+1754',
            'value' => '1754',
            'language' => 'US',
            'label' => '+1754 · United States',
        ],
        [
            'prefix' => '+1757',
            'value' => '1757',
            'language' => 'US',
            'label' => '+1757 · United States',
        ],
        [
            'prefix' => '+1758',
            'value' => '1758',
            'language' => 'LC',
            'label' => '+1758 · Saint Lucia',
        ],
        [
            'prefix' => '+1760',
            'value' => '1760',
            'language' => 'US',
            'label' => '+1760 · United States',
        ],
        [
            'prefix' => '+1762',
            'value' => '1762',
            'language' => 'US',
            'label' => '+1762 · United States',
        ],
        [
            'prefix' => '+1763',
            'value' => '1763',
            'language' => 'US',
            'label' => '+1763 · United States',
        ],
        [
            'prefix' => '+1765',
            'value' => '1765',
            'language' => 'US',
            'label' => '+1765 · United States',
        ],
        [
            'prefix' => '+1767',
            'value' => '1767',
            'language' => 'DM',
            'label' => '+1767 · Dominica',
        ],
        [
            'prefix' => '+1769',
            'value' => '1769',
            'language' => 'US',
            'label' => '+1769 · United States',
        ],
        [
            'prefix' => '+1770',
            'value' => '1770',
            'language' => 'US',
            'label' => '+1770 · United States',
        ],
        [
            'prefix' => '+1771',
            'value' => '1771',
            'language' => 'US',
            'label' => '+1771 · United States',
        ],
        [
            'prefix' => '+1772',
            'value' => '1772',
            'language' => 'US',
            'label' => '+1772 · United States',
        ],
        [
            'prefix' => '+1773',
            'value' => '1773',
            'language' => 'US',
            'label' => '+1773 · United States',
        ],
        [
            'prefix' => '+1774',
            'value' => '1774',
            'language' => 'US',
            'label' => '+1774 · United States',
        ],
        [
            'prefix' => '+1775',
            'value' => '1775',
            'language' => 'US',
            'label' => '+1775 · United States',
        ],
        [
            'prefix' => '+1778',
            'value' => '1778',
            'language' => 'CA',
            'label' => '+1778 · Canada',
        ],
        [
            'prefix' => '+1779',
            'value' => '1779',
            'language' => 'US',
            'label' => '+1779 · United States',
        ],
        [
            'prefix' => '+1780',
            'value' => '1780',
            'language' => 'CA',
            'label' => '+1780 · Canada',
        ],
        [
            'prefix' => '+1781',
            'value' => '1781',
            'language' => 'US',
            'label' => '+1781 · United States',
        ],
        [
            'prefix' => '+1782',
            'value' => '1782',
            'language' => 'CA',
            'label' => '+1782 · Canada',
        ],
        [
            'prefix' => '+1784',
            'value' => '1784',
            'language' => 'VC',
            'label' => '+1784 · Saint Vincent and the Grenadines',
        ],
        [
            'prefix' => '+1785',
            'value' => '1785',
            'language' => 'US',
            'label' => '+1785 · United States',
        ],
        [
            'prefix' => '+1786',
            'value' => '1786',
            'language' => 'US',
            'label' => '+1786 · United States',
        ],
        [
            'prefix' => '+1787',
            'value' => '1787',
            'language' => 'PR',
            'label' => '+1787 · Puerto Rico',
        ],
        [
            'prefix' => '+1801',
            'value' => '1801',
            'language' => 'US',
            'label' => '+1801 · United States',
        ],
        [
            'prefix' => '+1802',
            'value' => '1802',
            'language' => 'US',
            'label' => '+1802 · United States',
        ],
        [
            'prefix' => '+1803',
            'value' => '1803',
            'language' => 'US',
            'label' => '+1803 · United States',
        ],
        [
            'prefix' => '+1804',
            'value' => '1804',
            'language' => 'US',
            'label' => '+1804 · United States',
        ],
        [
            'prefix' => '+1805',
            'value' => '1805',
            'language' => 'US',
            'label' => '+1805 · United States',
        ],
        [
            'prefix' => '+1806',
            'value' => '1806',
            'language' => 'US',
            'label' => '+1806 · United States',
        ],
        [
            'prefix' => '+1807',
            'value' => '1807',
            'language' => 'CA',
            'label' => '+1807 · Canada',
        ],
        [
            'prefix' => '+1808',
            'value' => '1808',
            'language' => 'US',
            'label' => '+1808 · United States',
        ],
        [
            'prefix' => '+1809',
            'value' => '1809',
            'language' => 'DO',
            'label' => '+1809 · Dominican Republic',
        ],
        [
            'prefix' => '+1810',
            'value' => '1810',
            'language' => 'US',
            'label' => '+1810 · United States',
        ],
        [
            'prefix' => '+1812',
            'value' => '1812',
            'language' => 'US',
            'label' => '+1812 · United States',
        ],
        [
            'prefix' => '+1813',
            'value' => '1813',
            'language' => 'US',
            'label' => '+1813 · United States',
        ],
        [
            'prefix' => '+1814',
            'value' => '1814',
            'language' => 'US',
            'label' => '+1814 · United States',
        ],
        [
            'prefix' => '+1815',
            'value' => '1815',
            'language' => 'US',
            'label' => '+1815 · United States',
        ],
        [
            'prefix' => '+1816',
            'value' => '1816',
            'language' => 'US',
            'label' => '+1816 · United States',
        ],
        [
            'prefix' => '+1817',
            'value' => '1817',
            'language' => 'US',
            'label' => '+1817 · United States',
        ],
        [
            'prefix' => '+1818',
            'value' => '1818',
            'language' => 'US',
            'label' => '+1818 · United States',
        ],
        [
            'prefix' => '+1819',
            'value' => '1819',
            'language' => 'CA',
            'label' => '+1819 · Canada',
        ],
        [
            'prefix' => '+1820',
            'value' => '1820',
            'language' => 'US',
            'label' => '+1820 · United States',
        ],
        [
            'prefix' => '+1825',
            'value' => '1825',
            'language' => 'CA',
            'label' => '+1825 · Canada',
        ],
        [
            'prefix' => '+1826',
            'value' => '1826',
            'language' => 'US',
            'label' => '+1826 · United States',
        ],
        [
            'prefix' => '+1828',
            'value' => '1828',
            'language' => 'US',
            'label' => '+1828 · United States',
        ],
        [
            'prefix' => '+1829',
            'value' => '1829',
            'language' => 'DO',
            'label' => '+1829 · Dominican Republic',
        ],
        [
            'prefix' => '+1830',
            'value' => '1830',
            'language' => 'US',
            'label' => '+1830 · United States',
        ],
        [
            'prefix' => '+1831',
            'value' => '1831',
            'language' => 'US',
            'label' => '+1831 · United States',
        ],
        [
            'prefix' => '+1832',
            'value' => '1832',
            'language' => 'US',
            'label' => '+1832 · United States',
        ],
        [
            'prefix' => '+1838',
            'value' => '1838',
            'language' => 'US',
            'label' => '+1838 · United States',
        ],
        [
            'prefix' => '+1839',
            'value' => '1839',
            'language' => 'US',
            'label' => '+1839 · United States',
        ],
        [
            'prefix' => '+1840',
            'value' => '1840',
            'language' => 'US',
            'label' => '+1840 · United States',
        ],
        [
            'prefix' => '+1843',
            'value' => '1843',
            'language' => 'US',
            'label' => '+1843 · United States',
        ],
        [
            'prefix' => '+1845',
            'value' => '1845',
            'language' => 'US',
            'label' => '+1845 · United States',
        ],
        [
            'prefix' => '+1847',
            'value' => '1847',
            'language' => 'US',
            'label' => '+1847 · United States',
        ],
        [
            'prefix' => '+1848',
            'value' => '1848',
            'language' => 'US',
            'label' => '+1848 · United States',
        ],
        [
            'prefix' => '+1849',
            'value' => '1849',
            'language' => 'DO',
            'label' => '+1849 · Dominican Republic',
        ],
        [
            'prefix' => '+1850',
            'value' => '1850',
            'language' => 'US',
            'label' => '+1850 · United States',
        ],
        [
            'prefix' => '+1854',
            'value' => '1854',
            'language' => 'US',
            'label' => '+1854 · United States',
        ],
        [
            'prefix' => '+1856',
            'value' => '1856',
            'language' => 'US',
            'label' => '+1856 · United States',
        ],
        [
            'prefix' => '+1857',
            'value' => '1857',
            'language' => 'US',
            'label' => '+1857 · United States',
        ],
        [
            'prefix' => '+1858',
            'value' => '1858',
            'language' => 'US',
            'label' => '+1858 · United States',
        ],
        [
            'prefix' => '+1859',
            'value' => '1859',
            'language' => 'US',
            'label' => '+1859 · United States',
        ],
        [
            'prefix' => '+1860',
            'value' => '1860',
            'language' => 'US',
            'label' => '+1860 · United States',
        ],
        [
            'prefix' => '+1862',
            'value' => '1862',
            'language' => 'US',
            'label' => '+1862 · United States',
        ],
        [
            'prefix' => '+1863',
            'value' => '1863',
            'language' => 'US',
            'label' => '+1863 · United States',
        ],
        [
            'prefix' => '+1864',
            'value' => '1864',
            'language' => 'US',
            'label' => '+1864 · United States',
        ],
        [
            'prefix' => '+1865',
            'value' => '1865',
            'language' => 'US',
            'label' => '+1865 · United States',
        ],
        [
            'prefix' => '+1867',
            'value' => '1867',
            'language' => 'CA',
            'label' => '+1867 · Canada',
        ],
        [
            'prefix' => '+1868',
            'value' => '1868',
            'language' => 'TT',
            'label' => '+1868 · Trinidad and Tobago',
        ],
        [
            'prefix' => '+1869',
            'value' => '1869',
            'language' => 'KN',
            'label' => '+1869 · Saint Kitts and Nevis',
        ],
        [
            'prefix' => '+1870',
            'value' => '1870',
            'language' => 'US',
            'label' => '+1870 · United States',
        ],
        [
            'prefix' => '+1872',
            'value' => '1872',
            'language' => 'US',
            'label' => '+1872 · United States',
        ],
        [
            'prefix' => '+1873',
            'value' => '1873',
            'language' => 'CA',
            'label' => '+1873 · Canada',
        ],
        [
            'prefix' => '+1876',
            'value' => '1876',
            'language' => 'JM',
            'label' => '+1876 · Jamaica',
        ],
        [
            'prefix' => '+1878',
            'value' => '1878',
            'language' => 'US',
            'label' => '+1878 · United States',
        ],
        [
            'prefix' => '+1879',
            'value' => '1879',
            'language' => 'CA',
            'label' => '+1879 · Canada',
        ],
        [
            'prefix' => '+1901',
            'value' => '1901',
            'language' => 'US',
            'label' => '+1901 · United States',
        ],
        [
            'prefix' => '+1902',
            'value' => '1902',
            'language' => 'CA',
            'label' => '+1902 · Canada',
        ],
        [
            'prefix' => '+1903',
            'value' => '1903',
            'language' => 'US',
            'label' => '+1903 · United States',
        ],
        [
            'prefix' => '+1904',
            'value' => '1904',
            'language' => 'US',
            'label' => '+1904 · United States',
        ],
        [
            'prefix' => '+1905',
            'value' => '1905',
            'language' => 'CA',
            'label' => '+1905 · Canada',
        ],
        [
            'prefix' => '+1906',
            'value' => '1906',
            'language' => 'US',
            'label' => '+1906 · United States',
        ],
        [
            'prefix' => '+1907',
            'value' => '1907',
            'language' => 'US',
            'label' => '+1907 · United States',
        ],
        [
            'prefix' => '+1908',
            'value' => '1908',
            'language' => 'US',
            'label' => '+1908 · United States',
        ],
        [
            'prefix' => '+1909',
            'value' => '1909',
            'language' => 'US',
            'label' => '+1909 · United States',
        ],
        [
            'prefix' => '+1910',
            'value' => '1910',
            'language' => 'US',
            'label' => '+1910 · United States',
        ],
        [
            'prefix' => '+1912',
            'value' => '1912',
            'language' => 'US',
            'label' => '+1912 · United States',
        ],
        [
            'prefix' => '+1913',
            'value' => '1913',
            'language' => 'US',
            'label' => '+1913 · United States',
        ],
        [
            'prefix' => '+1914',
            'value' => '1914',
            'language' => 'US',
            'label' => '+1914 · United States',
        ],
        [
            'prefix' => '+1915',
            'value' => '1915',
            'language' => 'US',
            'label' => '+1915 · United States',
        ],
        [
            'prefix' => '+1916',
            'value' => '1916',
            'language' => 'US',
            'label' => '+1916 · United States',
        ],
        [
            'prefix' => '+1917',
            'value' => '1917',
            'language' => 'US',
            'label' => '+1917 · United States',
        ],
        [
            'prefix' => '+1918',
            'value' => '1918',
            'language' => 'US',
            'label' => '+1918 · United States',
        ],
        [
            'prefix' => '+1919',
            'value' => '1919',
            'language' => 'US',
            'label' => '+1919 · United States',
        ],
        [
            'prefix' => '+1920',
            'value' => '1920',
            'language' => 'US',
            'label' => '+1920 · United States',
        ],
        [
            'prefix' => '+1925',
            'value' => '1925',
            'language' => 'US',
            'label' => '+1925 · United States',
        ],
        [
            'prefix' => '+1928',
            'value' => '1928',
            'language' => 'US',
            'label' => '+1928 · United States',
        ],
        [
            'prefix' => '+1929',
            'value' => '1929',
            'language' => 'US',
            'label' => '+1929 · United States',
        ],
        [
            'prefix' => '+1930',
            'value' => '1930',
            'language' => 'US',
            'label' => '+1930 · United States',
        ],
        [
            'prefix' => '+1931',
            'value' => '1931',
            'language' => 'US',
            'label' => '+1931 · United States',
        ],
        [
            'prefix' => '+1934',
            'value' => '1934',
            'language' => 'US',
            'label' => '+1934 · United States',
        ],
        [
            'prefix' => '+1936',
            'value' => '1936',
            'language' => 'US',
            'label' => '+1936 · United States',
        ],
        [
            'prefix' => '+1937',
            'value' => '1937',
            'language' => 'US',
            'label' => '+1937 · United States',
        ],
        [
            'prefix' => '+1938',
            'value' => '1938',
            'language' => 'US',
            'label' => '+1938 · United States',
        ],
        [
            'prefix' => '+1939',
            'value' => '1939',
            'language' => 'PR',
            'label' => '+1939 · Puerto Rico',
        ],
        [
            'prefix' => '+1940',
            'value' => '1940',
            'language' => 'US',
            'label' => '+1940 · United States',
        ],
        [
            'prefix' => '+1941',
            'value' => '1941',
            'language' => 'US',
            'label' => '+1941 · United States',
        ],
        [
            'prefix' => '+1942',
            'value' => '1942',
            'language' => 'CA',
            'label' => '+1942 · Canada',
        ],
        [
            'prefix' => '+1943',
            'value' => '1943',
            'language' => 'US',
            'label' => '+1943 · United States',
        ],
        [
            'prefix' => '+1945',
            'value' => '1945',
            'language' => 'US',
            'label' => '+1945 · United States',
        ],
        [
            'prefix' => '+1947',
            'value' => '1947',
            'language' => 'US',
            'label' => '+1947 · United States',
        ],
        [
            'prefix' => '+1948',
            'value' => '1948',
            'language' => 'US',
            'label' => '+1948 · United States',
        ],
        [
            'prefix' => '+1949',
            'value' => '1949',
            'language' => 'US',
            'label' => '+1949 · United States',
        ],
        [
            'prefix' => '+1951',
            'value' => '1951',
            'language' => 'US',
            'label' => '+1951 · United States',
        ],
        [
            'prefix' => '+1952',
            'value' => '1952',
            'language' => 'US',
            'label' => '+1952 · United States',
        ],
        [
            'prefix' => '+1954',
            'value' => '1954',
            'language' => 'US',
            'label' => '+1954 · United States',
        ],
        [
            'prefix' => '+1956',
            'value' => '1956',
            'language' => 'US',
            'label' => '+1956 · United States',
        ],
        [
            'prefix' => '+1959',
            'value' => '1959',
            'language' => 'US',
            'label' => '+1959 · United States',
        ],
        [
            'prefix' => '+1970',
            'value' => '1970',
            'language' => 'US',
            'label' => '+1970 · United States',
        ],
        [
            'prefix' => '+1971',
            'value' => '1971',
            'language' => 'US',
            'label' => '+1971 · United States',
        ],
        [
            'prefix' => '+1972',
            'value' => '1972',
            'language' => 'US',
            'label' => '+1972 · United States',
        ],
        [
            'prefix' => '+1973',
            'value' => '1973',
            'language' => 'US',
            'label' => '+1973 · United States',
        ],
        [
            'prefix' => '+1975',
            'value' => '1975',
            'language' => 'US',
            'label' => '+1975 · United States',
        ],
        [
            'prefix' => '+1978',
            'value' => '1978',
            'language' => 'US',
            'label' => '+1978 · United States',
        ],
        [
            'prefix' => '+1979',
            'value' => '1979',
            'language' => 'US',
            'label' => '+1979 · United States',
        ],
        [
            'prefix' => '+1980',
            'value' => '1980',
            'language' => 'US',
            'label' => '+1980 · United States',
        ],
        [
            'prefix' => '+1983',
            'value' => '1983',
            'language' => 'US',
            'label' => '+1983 · United States',
        ],
        [
            'prefix' => '+1984',
            'value' => '1984',
            'language' => 'US',
            'label' => '+1984 · United States',
        ],
        [
            'prefix' => '+1985',
            'value' => '1985',
            'language' => 'US',
            'label' => '+1985 · United States',
        ],
        [
            'prefix' => '+1986',
            'value' => '1986',
            'language' => 'US',
            'label' => '+1986 · United States',
        ],
        [
            'prefix' => '+1989',
            'value' => '1989',
            'language' => 'US',
            'label' => '+1989 · United States',
        ],
        [
            'prefix' => '+20',
            'value' => '20',
            'language' => 'EG',
            'label' => '+20 · Egypt',
        ],
        [
            'prefix' => '+211',
            'value' => '211',
            'language' => 'SS',
            'label' => '+211 · South Sudan',
        ],
        [
            'prefix' => '+212',
            'value' => '212',
            'language' => 'MA',
            'label' => '+212 · Morocco',
        ],
        [
            'prefix' => '+2125288',
            'value' => '2125288',
            'language' => 'EH',
            'label' => '+2125288 · Western Sahara',
        ],
        [
            'prefix' => '+2125289',
            'value' => '2125289',
            'language' => 'EH',
            'label' => '+2125289 · Western Sahara',
        ],
        [
            'prefix' => '+213',
            'value' => '213',
            'language' => 'DZ',
            'label' => '+213 · Algeria',
        ],
        [
            'prefix' => '+216',
            'value' => '216',
            'language' => 'TN',
            'label' => '+216 · Tunisia',
        ],
        [
            'prefix' => '+218',
            'value' => '218',
            'language' => 'LY',
            'label' => '+218 · Libya',
        ],
        [
            'prefix' => '+220',
            'value' => '220',
            'language' => 'GM',
            'label' => '+220 · Gambia',
        ],
        [
            'prefix' => '+221',
            'value' => '221',
            'language' => 'SN',
            'label' => '+221 · Senegal',
        ],
        [
            'prefix' => '+222',
            'value' => '222',
            'language' => 'MR',
            'label' => '+222 · Mauritania',
        ],
        [
            'prefix' => '+223',
            'value' => '223',
            'language' => 'ML',
            'label' => '+223 · Mali',
        ],
        [
            'prefix' => '+224',
            'value' => '224',
            'language' => 'GN',
            'label' => '+224 · Guinea',
        ],
        [
            'prefix' => '+225',
            'value' => '225',
            'language' => 'CI',
            'label' => '+225 · Ivory Coast',
        ],
        [
            'prefix' => '+226',
            'value' => '226',
            'language' => 'BF',
            'label' => '+226 · Burkina Faso',
        ],
        [
            'prefix' => '+227',
            'value' => '227',
            'language' => 'NE',
            'label' => '+227 · Niger',
        ],
        [
            'prefix' => '+228',
            'value' => '228',
            'language' => 'TG',
            'label' => '+228 · Togo',
        ],
        [
            'prefix' => '+229',
            'value' => '229',
            'language' => 'BJ',
            'label' => '+229 · Benin',
        ],
        [
            'prefix' => '+230',
            'value' => '230',
            'language' => 'MU',
            'label' => '+230 · Mauritius',
        ],
        [
            'prefix' => '+231',
            'value' => '231',
            'language' => 'LR',
            'label' => '+231 · Liberia',
        ],
        [
            'prefix' => '+232',
            'value' => '232',
            'language' => 'SL',
            'label' => '+232 · Sierra Leone',
        ],
        [
            'prefix' => '+233',
            'value' => '233',
            'language' => 'GH',
            'label' => '+233 · Ghana',
        ],
        [
            'prefix' => '+234',
            'value' => '234',
            'language' => 'NG',
            'label' => '+234 · Nigeria',
        ],
        [
            'prefix' => '+235',
            'value' => '235',
            'language' => 'TD',
            'label' => '+235 · Chad',
        ],
        [
            'prefix' => '+236',
            'value' => '236',
            'language' => 'CF',
            'label' => '+236 · Central African Republic',
        ],
        [
            'prefix' => '+237',
            'value' => '237',
            'language' => 'CM',
            'label' => '+237 · Cameroon',
        ],
        [
            'prefix' => '+238',
            'value' => '238',
            'language' => 'CV',
            'label' => '+238 · Cape Verde',
        ],
        [
            'prefix' => '+239',
            'value' => '239',
            'language' => 'ST',
            'label' => '+239 · São Tomé and Príncipe',
        ],
        [
            'prefix' => '+240',
            'value' => '240',
            'language' => 'GQ',
            'label' => '+240 · Equatorial Guinea',
        ],
        [
            'prefix' => '+241',
            'value' => '241',
            'language' => 'GA',
            'label' => '+241 · Gabon',
        ],
        [
            'prefix' => '+242',
            'value' => '242',
            'language' => 'CG',
            'label' => '+242 · Congo',
        ],
        [
            'prefix' => '+243',
            'value' => '243',
            'language' => 'CD',
            'label' => '+243 · DR Congo',
        ],
        [
            'prefix' => '+244',
            'value' => '244',
            'language' => 'AO',
            'label' => '+244 · Angola',
        ],
        [
            'prefix' => '+245',
            'value' => '245',
            'language' => 'GW',
            'label' => '+245 · Guinea-Bissau',
        ],
        [
            'prefix' => '+246',
            'value' => '246',
            'language' => 'IO',
            'label' => '+246 · British Indian Ocean Territory',
        ],
        [
            'prefix' => '+247',
            'value' => '247',
            'language' => 'SH',
            'label' => '+247 · Saint Helena, Ascension and Tristan da Cunha',
        ],
        [
            'prefix' => '+248',
            'value' => '248',
            'language' => 'SC',
            'label' => '+248 · Seychelles',
        ],
        [
            'prefix' => '+249',
            'value' => '249',
            'language' => 'SD',
            'label' => '+249 · Sudan',
        ],
        [
            'prefix' => '+250',
            'value' => '250',
            'language' => 'RW',
            'label' => '+250 · Rwanda',
        ],
        [
            'prefix' => '+251',
            'value' => '251',
            'language' => 'ET',
            'label' => '+251 · Ethiopia',
        ],
        [
            'prefix' => '+252',
            'value' => '252',
            'language' => 'SO',
            'label' => '+252 · Somalia',
        ],
        [
            'prefix' => '+253',
            'value' => '253',
            'language' => 'DJ',
            'label' => '+253 · Djibouti',
        ],
        [
            'prefix' => '+254',
            'value' => '254',
            'language' => 'KE',
            'label' => '+254 · Kenya',
        ],
        [
            'prefix' => '+255',
            'value' => '255',
            'language' => 'TZ',
            'label' => '+255 · Tanzania',
        ],
        [
            'prefix' => '+256',
            'value' => '256',
            'language' => 'UG',
            'label' => '+256 · Uganda',
        ],
        [
            'prefix' => '+257',
            'value' => '257',
            'language' => 'BI',
            'label' => '+257 · Burundi',
        ],
        [
            'prefix' => '+258',
            'value' => '258',
            'language' => 'MZ',
            'label' => '+258 · Mozambique',
        ],
        [
            'prefix' => '+260',
            'value' => '260',
            'language' => 'ZM',
            'label' => '+260 · Zambia',
        ],
        [
            'prefix' => '+261',
            'value' => '261',
            'language' => 'MG',
            'label' => '+261 · Madagascar',
        ],
        [
            'prefix' => '+262',
            'value' => '262',
            'language' => 'TF',
            'label' => '+262 · French Southern and Antarctic Lands',
        ],
        [
            'prefix' => '+262',
            'value' => '262',
            'language' => 'YT',
            'label' => '+262 · Mayotte',
        ],
        [
            'prefix' => '+262',
            'value' => '262',
            'language' => 'RE',
            'label' => '+262 · Réunion',
        ],
        [
            'prefix' => '+263',
            'value' => '263',
            'language' => 'ZW',
            'label' => '+263 · Zimbabwe',
        ],
        [
            'prefix' => '+264',
            'value' => '264',
            'language' => 'NA',
            'label' => '+264 · Namibia',
        ],
        [
            'prefix' => '+265',
            'value' => '265',
            'language' => 'MW',
            'label' => '+265 · Malawi',
        ],
        [
            'prefix' => '+266',
            'value' => '266',
            'language' => 'LS',
            'label' => '+266 · Lesotho',
        ],
        [
            'prefix' => '+267',
            'value' => '267',
            'language' => 'BW',
            'label' => '+267 · Botswana',
        ],
        [
            'prefix' => '+268',
            'value' => '268',
            'language' => 'SZ',
            'label' => '+268 · Eswatini',
        ],
        [
            'prefix' => '+268',
            'value' => '268',
            'language' => 'UM',
            'label' => '+268 · United States Minor Outlying Islands',
        ],
        [
            'prefix' => '+269',
            'value' => '269',
            'language' => 'KM',
            'label' => '+269 · Comoros',
        ],
        [
            'prefix' => '+27',
            'value' => '27',
            'language' => 'ZA',
            'label' => '+27 · South Africa',
        ],
        [
            'prefix' => '+290',
            'value' => '290',
            'language' => 'SH',
            'label' => '+290 · Saint Helena, Ascension and Tristan da Cunha',
        ],
        [
            'prefix' => '+291',
            'value' => '291',
            'language' => 'ER',
            'label' => '+291 · Eritrea',
        ],
        [
            'prefix' => '+297',
            'value' => '297',
            'language' => 'AW',
            'label' => '+297 · Aruba',
        ],
        [
            'prefix' => '+298',
            'value' => '298',
            'language' => 'FO',
            'label' => '+298 · Faroe Islands',
        ],
        [
            'prefix' => '+299',
            'value' => '299',
            'language' => 'GL',
            'label' => '+299 · Greenland',
        ],
        [
            'prefix' => '+30',
            'value' => '30',
            'language' => 'GR',
            'label' => '+30 · Greece',
        ],
        [
            'prefix' => '+31',
            'value' => '31',
            'language' => 'NL',
            'label' => '+31 · Netherlands',
        ],
        [
            'prefix' => '+32',
            'value' => '32',
            'language' => 'BE',
            'label' => '+32 · Belgium',
        ],
        [
            'prefix' => '+33',
            'value' => '33',
            'language' => 'FR',
            'label' => '+33 · France',
        ],
        [
            'prefix' => '+34',
            'value' => '34',
            'language' => 'ES',
            'label' => '+34 · Spain',
        ],
        [
            'prefix' => '+350',
            'value' => '350',
            'language' => 'GI',
            'label' => '+350 · Gibraltar',
        ],
        [
            'prefix' => '+351',
            'value' => '351',
            'language' => 'PT',
            'label' => '+351 · Portugal',
        ],
        [
            'prefix' => '+352',
            'value' => '352',
            'language' => 'LU',
            'label' => '+352 · Luxembourg',
        ],
        [
            'prefix' => '+353',
            'value' => '353',
            'language' => 'IE',
            'label' => '+353 · Ireland',
        ],
        [
            'prefix' => '+354',
            'value' => '354',
            'language' => 'IS',
            'label' => '+354 · Iceland',
        ],
        [
            'prefix' => '+355',
            'value' => '355',
            'language' => 'AL',
            'label' => '+355 · Albania',
        ],
        [
            'prefix' => '+356',
            'value' => '356',
            'language' => 'MT',
            'label' => '+356 · Malta',
        ],
        [
            'prefix' => '+357',
            'value' => '357',
            'language' => 'CY',
            'label' => '+357 · Cyprus',
        ],
        [
            'prefix' => '+358',
            'value' => '358',
            'language' => 'FI',
            'label' => '+358 · Finland',
        ],
        [
            'prefix' => '+35818',
            'value' => '35818',
            'language' => 'AX',
            'label' => '+35818 · Åland Islands',
        ],
        [
            'prefix' => '+359',
            'value' => '359',
            'language' => 'BG',
            'label' => '+359 · Bulgaria',
        ],
        [
            'prefix' => '+36',
            'value' => '36',
            'language' => 'HU',
            'label' => '+36 · Hungary',
        ],
        [
            'prefix' => '+370',
            'value' => '370',
            'language' => 'LT',
            'label' => '+370 · Lithuania',
        ],
        [
            'prefix' => '+371',
            'value' => '371',
            'language' => 'LV',
            'label' => '+371 · Latvia',
        ],
        [
            'prefix' => '+372',
            'value' => '372',
            'language' => 'EE',
            'label' => '+372 · Estonia',
        ],
        [
            'prefix' => '+373',
            'value' => '373',
            'language' => 'MD',
            'label' => '+373 · Moldova',
        ],
        [
            'prefix' => '+374',
            'value' => '374',
            'language' => 'AM',
            'label' => '+374 · Armenia',
        ],
        [
            'prefix' => '+375',
            'value' => '375',
            'language' => 'BY',
            'label' => '+375 · Belarus',
        ],
        [
            'prefix' => '+376',
            'value' => '376',
            'language' => 'AD',
            'label' => '+376 · Andorra',
        ],
        [
            'prefix' => '+377',
            'value' => '377',
            'language' => 'MC',
            'label' => '+377 · Monaco',
        ],
        [
            'prefix' => '+378',
            'value' => '378',
            'language' => 'SM',
            'label' => '+378 · San Marino',
        ],
        [
            'prefix' => '+379',
            'value' => '379',
            'language' => 'VA',
            'label' => '+379 · Vatican City',
        ],
        [
            'prefix' => '+380',
            'value' => '380',
            'language' => 'UA',
            'label' => '+380 · Ukraine',
        ],
        [
            'prefix' => '+381',
            'value' => '381',
            'language' => 'RS',
            'label' => '+381 · Serbia',
        ],
        [
            'prefix' => '+382',
            'value' => '382',
            'language' => 'ME',
            'label' => '+382 · Montenegro',
        ],
        [
            'prefix' => '+383',
            'value' => '383',
            'language' => 'XK',
            'label' => '+383 · Kosovo',
        ],
        [
            'prefix' => '+385',
            'value' => '385',
            'language' => 'HR',
            'label' => '+385 · Croatia',
        ],
        [
            'prefix' => '+386',
            'value' => '386',
            'language' => 'SI',
            'label' => '+386 · Slovenia',
        ],
        [
            'prefix' => '+387',
            'value' => '387',
            'language' => 'BA',
            'label' => '+387 · Bosnia and Herzegovina',
        ],
        [
            'prefix' => '+389',
            'value' => '389',
            'language' => 'MK',
            'label' => '+389 · North Macedonia',
        ],
        [
            'prefix' => '+3906698',
            'value' => '3906698',
            'language' => 'VA',
            'label' => '+3906698 · Vatican City',
        ],
        [
            'prefix' => '+40',
            'value' => '40',
            'language' => 'RO',
            'label' => '+40 · Romania',
        ],
        [
            'prefix' => '+41',
            'value' => '41',
            'language' => 'CH',
            'label' => '+41 · Switzerland',
        ],
        [
            'prefix' => '+420',
            'value' => '420',
            'language' => 'CZ',
            'label' => '+420 · Czechia',
        ],
        [
            'prefix' => '+421',
            'value' => '421',
            'language' => 'SK',
            'label' => '+421 · Slovakia',
        ],
        [
            'prefix' => '+423',
            'value' => '423',
            'language' => 'LI',
            'label' => '+423 · Liechtenstein',
        ],
        [
            'prefix' => '+43',
            'value' => '43',
            'language' => 'AT',
            'label' => '+43 · Austria',
        ],
        [
            'prefix' => '+44',
            'value' => '44',
            'language' => 'GG',
            'label' => '+44 · Guernsey',
        ],
        [
            'prefix' => '+44',
            'value' => '44',
            'language' => 'IM',
            'label' => '+44 · Isle of Man',
        ],
        [
            'prefix' => '+44',
            'value' => '44',
            'language' => 'JE',
            'label' => '+44 · Jersey',
        ],
        [
            'prefix' => '+44',
            'value' => '44',
            'language' => 'GB',
            'label' => '+44 · United Kingdom',
        ],
        [
            'prefix' => '+45',
            'value' => '45',
            'language' => 'DK',
            'label' => '+45 · Denmark',
        ],
        [
            'prefix' => '+46',
            'value' => '46',
            'language' => 'SE',
            'label' => '+46 · Sweden',
        ],
        [
            'prefix' => '+47',
            'value' => '47',
            'language' => 'BV',
            'label' => '+47 · Bouvet Island',
        ],
        [
            'prefix' => '+47',
            'value' => '47',
            'language' => 'NO',
            'label' => '+47 · Norway',
        ],
        [
            'prefix' => '+4779',
            'value' => '4779',
            'language' => 'SJ',
            'label' => '+4779 · Svalbard and Jan Mayen',
        ],
        [
            'prefix' => '+48',
            'value' => '48',
            'language' => 'PL',
            'label' => '+48 · Poland',
        ],
        [
            'prefix' => '+49',
            'value' => '49',
            'language' => 'DE',
            'label' => '+49 · Germany',
        ],
        [
            'prefix' => '+500',
            'value' => '500',
            'language' => 'FK',
            'label' => '+500 · Falkland Islands',
        ],
        [
            'prefix' => '+500',
            'value' => '500',
            'language' => 'GS',
            'label' => '+500 · South Georgia',
        ],
        [
            'prefix' => '+501',
            'value' => '501',
            'language' => 'BZ',
            'label' => '+501 · Belize',
        ],
        [
            'prefix' => '+502',
            'value' => '502',
            'language' => 'GT',
            'label' => '+502 · Guatemala',
        ],
        [
            'prefix' => '+503',
            'value' => '503',
            'language' => 'SV',
            'label' => '+503 · El Salvador',
        ],
        [
            'prefix' => '+504',
            'value' => '504',
            'language' => 'HN',
            'label' => '+504 · Honduras',
        ],
        [
            'prefix' => '+505',
            'value' => '505',
            'language' => 'NI',
            'label' => '+505 · Nicaragua',
        ],
        [
            'prefix' => '+506',
            'value' => '506',
            'language' => 'CR',
            'label' => '+506 · Costa Rica',
        ],
        [
            'prefix' => '+507',
            'value' => '507',
            'language' => 'PA',
            'label' => '+507 · Panama',
        ],
        [
            'prefix' => '+508',
            'value' => '508',
            'language' => 'PM',
            'label' => '+508 · Saint Pierre and Miquelon',
        ],
        [
            'prefix' => '+509',
            'value' => '509',
            'language' => 'HT',
            'label' => '+509 · Haiti',
        ],
        [
            'prefix' => '+51',
            'value' => '51',
            'language' => 'PE',
            'label' => '+51 · Peru',
        ],
        [
            'prefix' => '+52',
            'value' => '52',
            'language' => 'MX',
            'label' => '+52 · Mexico',
        ],
        [
            'prefix' => '+53',
            'value' => '53',
            'language' => 'CU',
            'label' => '+53 · Cuba',
        ],
        [
            'prefix' => '+54',
            'value' => '54',
            'language' => 'AR',
            'label' => '+54 · Argentina',
        ],
        [
            'prefix' => '+55',
            'value' => '55',
            'language' => 'BR',
            'label' => '+55 · Brazil',
        ],
        [
            'prefix' => '+56',
            'value' => '56',
            'language' => 'CL',
            'label' => '+56 · Chile',
        ],
        [
            'prefix' => '+57',
            'value' => '57',
            'language' => 'CO',
            'label' => '+57 · Colombia',
        ],
        [
            'prefix' => '+58',
            'value' => '58',
            'language' => 'VE',
            'label' => '+58 · Venezuela',
        ],
        [
            'prefix' => '+590',
            'value' => '590',
            'language' => 'GP',
            'label' => '+590 · Guadeloupe',
        ],
        [
            'prefix' => '+590',
            'value' => '590',
            'language' => 'BL',
            'label' => '+590 · Saint Barthélemy',
        ],
        [
            'prefix' => '+590',
            'value' => '590',
            'language' => 'MF',
            'label' => '+590 · Saint Martin',
        ],
        [
            'prefix' => '+591',
            'value' => '591',
            'language' => 'BO',
            'label' => '+591 · Bolivia',
        ],
        [
            'prefix' => '+592',
            'value' => '592',
            'language' => 'GY',
            'label' => '+592 · Guyana',
        ],
        [
            'prefix' => '+593',
            'value' => '593',
            'language' => 'EC',
            'label' => '+593 · Ecuador',
        ],
        [
            'prefix' => '+594',
            'value' => '594',
            'language' => 'GF',
            'label' => '+594 · French Guiana',
        ],
        [
            'prefix' => '+595',
            'value' => '595',
            'language' => 'PY',
            'label' => '+595 · Paraguay',
        ],
        [
            'prefix' => '+596',
            'value' => '596',
            'language' => 'MQ',
            'label' => '+596 · Martinique',
        ],
        [
            'prefix' => '+597',
            'value' => '597',
            'language' => 'SR',
            'label' => '+597 · Suriname',
        ],
        [
            'prefix' => '+598',
            'value' => '598',
            'language' => 'UY',
            'label' => '+598 · Uruguay',
        ],
        [
            'prefix' => '+599',
            'value' => '599',
            'language' => 'BQ',
            'label' => '+599 · Caribbean Netherlands',
        ],
        [
            'prefix' => '+599',
            'value' => '599',
            'language' => 'CW',
            'label' => '+599 · Curaçao',
        ],
        [
            'prefix' => '+60',
            'value' => '60',
            'language' => 'MY',
            'label' => '+60 · Malaysia',
        ],
        [
            'prefix' => '+61',
            'value' => '61',
            'language' => 'AU',
            'label' => '+61 · Australia',
        ],
        [
            'prefix' => '+61',
            'value' => '61',
            'language' => 'CX',
            'label' => '+61 · Christmas Island',
        ],
        [
            'prefix' => '+61',
            'value' => '61',
            'language' => 'CC',
            'label' => '+61 · Cocos (Keeling) Islands',
        ],
        [
            'prefix' => '+62',
            'value' => '62',
            'language' => 'ID',
            'label' => '+62 · Indonesia',
        ],
        [
            'prefix' => '+63',
            'value' => '63',
            'language' => 'PH',
            'label' => '+63 · Philippines',
        ],
        [
            'prefix' => '+64',
            'value' => '64',
            'language' => 'NZ',
            'label' => '+64 · New Zealand',
        ],
        [
            'prefix' => '+64',
            'value' => '64',
            'language' => 'PN',
            'label' => '+64 · Pitcairn Islands',
        ],
        [
            'prefix' => '+65',
            'value' => '65',
            'language' => 'SG',
            'label' => '+65 · Singapore',
        ],
        [
            'prefix' => '+66',
            'value' => '66',
            'language' => 'TH',
            'label' => '+66 · Thailand',
        ],
        [
            'prefix' => '+670',
            'value' => '670',
            'language' => 'TL',
            'label' => '+670 · Timor-Leste',
        ],
        [
            'prefix' => '+672',
            'value' => '672',
            'language' => 'NF',
            'label' => '+672 · Norfolk Island',
        ],
        [
            'prefix' => '+673',
            'value' => '673',
            'language' => 'BN',
            'label' => '+673 · Brunei',
        ],
        [
            'prefix' => '+674',
            'value' => '674',
            'language' => 'NR',
            'label' => '+674 · Nauru',
        ],
        [
            'prefix' => '+675',
            'value' => '675',
            'language' => 'PG',
            'label' => '+675 · Papua New Guinea',
        ],
        [
            'prefix' => '+676',
            'value' => '676',
            'language' => 'TO',
            'label' => '+676 · Tonga',
        ],
        [
            'prefix' => '+677',
            'value' => '677',
            'language' => 'SB',
            'label' => '+677 · Solomon Islands',
        ],
        [
            'prefix' => '+678',
            'value' => '678',
            'language' => 'VU',
            'label' => '+678 · Vanuatu',
        ],
        [
            'prefix' => '+679',
            'value' => '679',
            'language' => 'FJ',
            'label' => '+679 · Fiji',
        ],
        [
            'prefix' => '+680',
            'value' => '680',
            'language' => 'PW',
            'label' => '+680 · Palau',
        ],
        [
            'prefix' => '+681',
            'value' => '681',
            'language' => 'WF',
            'label' => '+681 · Wallis and Futuna',
        ],
        [
            'prefix' => '+682',
            'value' => '682',
            'language' => 'CK',
            'label' => '+682 · Cook Islands',
        ],
        [
            'prefix' => '+683',
            'value' => '683',
            'language' => 'NU',
            'label' => '+683 · Niue',
        ],
        [
            'prefix' => '+685',
            'value' => '685',
            'language' => 'WS',
            'label' => '+685 · Samoa',
        ],
        [
            'prefix' => '+686',
            'value' => '686',
            'language' => 'KI',
            'label' => '+686 · Kiribati',
        ],
        [
            'prefix' => '+687',
            'value' => '687',
            'language' => 'NC',
            'label' => '+687 · New Caledonia',
        ],
        [
            'prefix' => '+688',
            'value' => '688',
            'language' => 'TV',
            'label' => '+688 · Tuvalu',
        ],
        [
            'prefix' => '+689',
            'value' => '689',
            'language' => 'PF',
            'label' => '+689 · French Polynesia',
        ],
        [
            'prefix' => '+690',
            'value' => '690',
            'language' => 'TK',
            'label' => '+690 · Tokelau',
        ],
        [
            'prefix' => '+691',
            'value' => '691',
            'language' => 'FM',
            'label' => '+691 · Micronesia',
        ],
        [
            'prefix' => '+692',
            'value' => '692',
            'language' => 'MH',
            'label' => '+692 · Marshall Islands',
        ],
        [
            'prefix' => '+73',
            'value' => '73',
            'language' => 'RU',
            'label' => '+73 · Russia',
        ],
        [
            'prefix' => '+74',
            'value' => '74',
            'language' => 'RU',
            'label' => '+74 · Russia',
        ],
        [
            'prefix' => '+75',
            'value' => '75',
            'language' => 'RU',
            'label' => '+75 · Russia',
        ],
        [
            'prefix' => '+76',
            'value' => '76',
            'language' => 'KZ',
            'label' => '+76 · Kazakhstan',
        ],
        [
            'prefix' => '+77',
            'value' => '77',
            'language' => 'KZ',
            'label' => '+77 · Kazakhstan',
        ],
        [
            'prefix' => '+78',
            'value' => '78',
            'language' => 'RU',
            'label' => '+78 · Russia',
        ],
        [
            'prefix' => '+79',
            'value' => '79',
            'language' => 'RU',
            'label' => '+79 · Russia',
        ],
        [
            'prefix' => '+81',
            'value' => '81',
            'language' => 'JP',
            'label' => '+81 · Japan',
        ],
        [
            'prefix' => '+82',
            'value' => '82',
            'language' => 'KR',
            'label' => '+82 · South Korea',
        ],
        [
            'prefix' => '+84',
            'value' => '84',
            'language' => 'VN',
            'label' => '+84 · Vietnam',
        ],
        [
            'prefix' => '+850',
            'value' => '850',
            'language' => 'KP',
            'label' => '+850 · North Korea',
        ],
        [
            'prefix' => '+852',
            'value' => '852',
            'language' => 'HK',
            'label' => '+852 · Hong Kong',
        ],
        [
            'prefix' => '+853',
            'value' => '853',
            'language' => 'MO',
            'label' => '+853 · Macau',
        ],
        [
            'prefix' => '+855',
            'value' => '855',
            'language' => 'KH',
            'label' => '+855 · Cambodia',
        ],
        [
            'prefix' => '+856',
            'value' => '856',
            'language' => 'LA',
            'label' => '+856 · Laos',
        ],
        [
            'prefix' => '+86',
            'value' => '86',
            'language' => 'CN',
            'label' => '+86 · China',
        ],
        [
            'prefix' => '+880',
            'value' => '880',
            'language' => 'BD',
            'label' => '+880 · Bangladesh',
        ],
        [
            'prefix' => '+886',
            'value' => '886',
            'language' => 'TW',
            'label' => '+886 · Taiwan',
        ],
        [
            'prefix' => '+90',
            'value' => '90',
            'language' => 'TR',
            'label' => '+90 · Türkiye',
        ],
        [
            'prefix' => '+91',
            'value' => '91',
            'language' => 'IN',
            'label' => '+91 · India',
        ],
        [
            'prefix' => '+92',
            'value' => '92',
            'language' => 'PK',
            'label' => '+92 · Pakistan',
        ],
        [
            'prefix' => '+93',
            'value' => '93',
            'language' => 'AF',
            'label' => '+93 · Afghanistan',
        ],
        [
            'prefix' => '+94',
            'value' => '94',
            'language' => 'LK',
            'label' => '+94 · Sri Lanka',
        ],
        [
            'prefix' => '+95',
            'value' => '95',
            'language' => 'MM',
            'label' => '+95 · Myanmar',
        ],
        [
            'prefix' => '+960',
            'value' => '960',
            'language' => 'MV',
            'label' => '+960 · Maldives',
        ],
        [
            'prefix' => '+961',
            'value' => '961',
            'language' => 'LB',
            'label' => '+961 · Lebanon',
        ],
        [
            'prefix' => '+962',
            'value' => '962',
            'language' => 'JO',
            'label' => '+962 · Jordan',
        ],
        [
            'prefix' => '+963',
            'value' => '963',
            'language' => 'SY',
            'label' => '+963 · Syria',
        ],
        [
            'prefix' => '+964',
            'value' => '964',
            'language' => 'IQ',
            'label' => '+964 · Iraq',
        ],
        [
            'prefix' => '+965',
            'value' => '965',
            'language' => 'KW',
            'label' => '+965 · Kuwait',
        ],
        [
            'prefix' => '+966',
            'value' => '966',
            'language' => 'SA',
            'label' => '+966 · Saudi Arabia',
        ],
        [
            'prefix' => '+967',
            'value' => '967',
            'language' => 'YE',
            'label' => '+967 · Yemen',
        ],
        [
            'prefix' => '+968',
            'value' => '968',
            'language' => 'OM',
            'label' => '+968 · Oman',
        ],
        [
            'prefix' => '+970',
            'value' => '970',
            'language' => 'PS',
            'label' => '+970 · Palestine',
        ],
        [
            'prefix' => '+971',
            'value' => '971',
            'language' => 'AE',
            'label' => '+971 · United Arab Emirates',
        ],
        [
            'prefix' => '+972',
            'value' => '972',
            'language' => 'IL',
            'label' => '+972 · Israel',
        ],
        [
            'prefix' => '+973',
            'value' => '973',
            'language' => 'BH',
            'label' => '+973 · Bahrain',
        ],
        [
            'prefix' => '+974',
            'value' => '974',
            'language' => 'QA',
            'label' => '+974 · Qatar',
        ],
        [
            'prefix' => '+975',
            'value' => '975',
            'language' => 'BT',
            'label' => '+975 · Bhutan',
        ],
        [
            'prefix' => '+976',
            'value' => '976',
            'language' => 'MN',
            'label' => '+976 · Mongolia',
        ],
        [
            'prefix' => '+977',
            'value' => '977',
            'language' => 'NP',
            'label' => '+977 · Nepal',
        ],
        [
            'prefix' => '+98',
            'value' => '98',
            'language' => 'IR',
            'label' => '+98 · Iran',
        ],
        [
            'prefix' => '+992',
            'value' => '992',
            'language' => 'TJ',
            'label' => '+992 · Tajikistan',
        ],
        [
            'prefix' => '+993',
            'value' => '993',
            'language' => 'TM',
            'label' => '+993 · Turkmenistan',
        ],
        [
            'prefix' => '+994',
            'value' => '994',
            'language' => 'AZ',
            'label' => '+994 · Azerbaijan',
        ],
        [
            'prefix' => '+995',
            'value' => '995',
            'language' => 'GE',
            'label' => '+995 · Georgia',
        ],
        [
            'prefix' => '+996',
            'value' => '996',
            'language' => 'KG',
            'label' => '+996 · Kyrgyzstan',
        ],
        [
            'prefix' => '+998',
            'value' => '998',
            'language' => 'UZ',
            'label' => '+998 · Uzbekistan',
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
        return self::DEFAULT_PHONE_PREFIXES;
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

