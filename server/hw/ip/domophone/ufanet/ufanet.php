<?php

namespace hw\ip\domophone\ufanet;

use CURLFile;
use Generator;
use hw\Interface\{
    DisplayTextInterface,
    FreePassInterface,
    GateModeInterface,
    LanguageInterface,
};
use hw\ip\domophone\domophone;

/**
 * Abstract class representing an Ufanet intercom.
 */
abstract class ufanet extends domophone implements
    DisplayTextInterface,
    FreePassInterface,
    GateModeInterface,
    LanguageInterface
{
    use \hw\ip\common\ufanet\ufanet {
        transformDbConfig as protected commonTransformDbConfig;
    }

    /**
     * @var array Set of parameters sent to the intercom for different CMS models.
     */
    protected const CMS_PARAMS = [
        'BK-100' => ['type' => 'VIZIT', 'mode' => 2], // TODO: check mode 1 and mode 2
        'BK-400' => ['type' => 'VIZIT', 'mode' => 3],
        'COM-25U' => ['type' => 'METAKOM'],
        'COM-100U' => ['type' => 'METAKOM'],
        'COM-220U' => ['type' => 'METAKOM'],
        'FACTORIAL 8x8' => ['type' => 'FACTORIAL'],
        'KKM-100S2' => ['type' => 'BEWARD_100'],
        'KKM-105' => ['type' => 'BEWARD_105_108'],
        'KKM-108' => ['type' => 'BEWARD_105_108'],
        'KM20-1' => ['type' => 'ELTIS', 'mode' => 1, 'edge' => 20],
        'KM100-7.1' => ['type' => 'ELTIS', 'mode' => 1, 'edge' => 100],
        'KM100-7.2' => ['type' => 'ELTIS', 'mode' => 1, 'edge' => 100],
        'KM100-7.3' => ['type' => 'ELTIS', 'mode' => 1, 'edge' => 100],
        'KM100-7.5' => ['type' => 'ELTIS', 'mode' => 1, 'edge' => 100],
        'KMG-100' => ['type' => 'CYFRAL', 'mode' => 1, 'edge' => 100],
        'QAD-100' => ['type' => 'DIGITAL'],
    ];

    protected const LINE_TEST_DURATION = 2;
    protected const RELAY_SWITCHING_DURATION = 1;
    protected const UNLOCK_DATE = '3000-01-01 00:00:00';

    /**
     * @var array|null $dialplans An array that holds dialplan information, which may be null if not loaded.
     */
    protected ?array $dialplans = null;

    /**
     * @var array|null $keys An array that holds keys (RFID, personal code, BLE) information,
     * which may be null if not loaded.
     */
    protected ?array $keys = null;

    protected ?string $cmsModelName = null;

    /**
     * @return array{index:string,value:array}
     */
    protected static function getMatrixCell(int $mapping, int $apartment): array
    {
        $hundreds = floor($mapping / 100);
        $tens = floor(($mapping - $hundreds * 100) / 10);
        $units = $mapping - ($hundreds * 100 + $tens * 10);

        $index = "$hundreds$tens$units";

        return [
            'index' => $index,
            'value' => [
                'hundreds' => $hundreds,
                'tens' => $tens,
                'units' => $units,
                'apartment' => $apartment,
            ],
        ];
    }

    /**
     * Converts an RFID code to the device's standard format.
     *
     * @param string $code The raw RFID code.
     * @return string The normalized RFID code.
     */
    protected static function getNormalizedRfid(string $code): string
    {
        return strtolower(ltrim($code, '0'));
    }

    public function addRfid(string $code, int $apartment = 0): void
    {
        $this->addKey(
            value: self::getNormalizedRfid($code),
            flat: 0,
            type: KeyType::RfidPersonal,
        );
    }

    public function addRfids(array $rfids): void
    {
        foreach ($rfids as $rfid) {
            $this->addRfid($rfid);
        }
    }

    public function configureApartment(
        int   $apartment,
        int   $code = 0,
        array $sipNumbers = [],
        bool  $cmsEnabled = true,
        array $cmsLevels = [],
    ): void
    {
        $this->loadDialplans();
        $this->deleteFlatPersonalCodes($apartment);

        if ($code !== 0) {
            $this->addKey($code, $apartment, KeyType::CodePersonal);
        }

        $this->dialplans[$apartment] = [
            'sip_number' => "$sipNumbers[0]" ?? '',
            'sip' => true,
            'analog' => $cmsEnabled,
            'map' => $this->dialplans[$apartment]['map'] ?? 0,
        ];
    }

    public function configureEncoding(): void
    {
        $this->apiCall('/cgi-bin/configManager.cgi', 'GET', [
            'action' => 'setConfig',

            // Audio stream
            'Encode[0].MainFormat[0].AudioEnable' => 'true',
            'Encode[0].MainFormat[0].Audio.Compression' => 'alaw',
            'Encode[0].MainFormat[0].Audio.Frequency' => 8000,

            // Video main stream
            'Encode[0].MainFormat[0].VideoEnable' => 'true',
            'Encode[0].MainFormat[0].Video.Compression' => 'h264',
            'Encode[0].MainFormat[0].Video.Profile' => 'Baseline',
            'Encode[0].MainFormat[0].Video.resolution' => '1280x720',
            'Encode[0].MainFormat[0].Video.FPS' => 15,
            'Encode[0].MainFormat[0].Video.GOP' => 1,
            'Encode[0].MainFormat[0].Video.GOPmode' => 'normal',
            'Encode[0].MainFormat[0].Video.BitRate' => 1024,
            'Encode[0].MainFormat[0].Video.BitRateControl' => 'vbr',

            // Video extra stream
            'Encode[0].ExtraFormat[0].VideoEnable' => 'false',
            'Encode[0].ExtraFormat[0].Video.Compression' => 'h264',
            'Encode[0].ExtraFormat[0].Video.resolution' => '640x352',
            'Encode[0].ExtraFormat[0].Video.FPS' => 15,
            'Encode[0].ExtraFormat[0].Video.GOP' => 1,
            'Encode[0].ExtraFormat[0].Video.GOPmode' => 'normal',
            'Encode[0].ExtraFormat[0].Video.BitRate' => 512,
            'Encode[0].ExtraFormat[0].Video.BitRateControl' => 'vbr',
        ]);
    }

    public function configureMatrix(array $matrix): void
    {
        $remappedMatrix = $this->remapMatrix($matrix);

        foreach ($this->getApartmentsDialplans(true) as $apartment => $dialplan) {
            foreach ($remappedMatrix as $cell) {
                if ($apartment === $cell['apartment']) {
                    $apartment = $cell['apartment'];
                    $line = $cell['hundreds'] * 100 + $cell['tens'] * 10 + $cell['units'];
                    $this->dialplans[$apartment]['map'] = $line;
                    continue 2;
                }
            }

            $this->dialplans[$apartment]['map'] = 0;
        }
    }

    public function configureSip(
        string $login,
        string $password,
        string $server,
        int    $port = 5060,
        bool   $stunEnabled = false,
        string $stunServer = '',
        int    $stunPort = 3478,
    ): void
    {
        $this->apiCall('/api/v1/configuration', 'PATCH', [
            'sip' => [
                'domain' => "$server:$port",
                'user' => $login,
                'password' => $password,
            ],
        ]);
    }

    public function configureUserAccount(string $password): void
    {
        // Empty implementation
    }

    public function deleteApartment(int $apartment = 0): void
    {
        $this->loadDialplans();

        ['map' => $analogReplace, 'analog' => $cmsEnabled] = $this->dialplans[$apartment];

        if ($analogReplace !== 0) {
            $this->dialplans[$apartment] = [
                'sip_number' => '',
                'sip' => false,
                'analog' => $cmsEnabled,
                'map' => $analogReplace,
            ];
        } else {
            unset($this->dialplans[$apartment]);
        }

        $this->deleteFlatPersonalCodes($apartment);
    }

    public function deleteRfid(string $code = ''): void
    {
        $this->loadKeys();

        if ($code === '') {
            $this->keys = array_diff_assoc(
                $this->keys,
                Key::filterByType($this->keys, KeyType::RfidPersonal),
            );
        } else {
            $normalizedRfid = self::getNormalizedRfid($code);

            if (!isset($this->keys[$normalizedRfid])) {
                return;
            }

            if (Key::getType($this->keys[$normalizedRfid]) === KeyType::RfidPersonal) {
                unset($this->keys[$normalizedRfid]);
            }
        }
    }

    public function getDisplayText(): array
    {
        return array_filter($this->apiCall('/api/v1/configuration')['display']['labels'] ?? []);
    }

    public function getDisplayTextLinesCount(): int
    {
        return 3;
    }

    public function getLineDiagnostics(int $apartment): string|int|float
    {
        $url = "/api/v1/apartments/$apartment/test";

        $this->apiCall($url, 'POST'); // Start test
        sleep(self::LINE_TEST_DURATION); // Wait test
        $resultRaw = $this->apiCall($url); // Get result

        return $resultRaw['result'] ?? '';
    }

    public function isFreePassEnabled(): bool
    {
        $doorSettings = $this->apiCall('/api/v1/configuration')['door'];
        return $doorSettings['unlock'] !== '' || $doorSettings['unlock2'] !== '';
    }

    public function isGateModeEnabled(): bool
    {
        ['type' => $type, 'mode' => $mode] = $this->apiCall('/api/v1/configuration')['commutator'];
        return $type === 'GATE' && $mode === 1;
    }

    public function openLock(int $lockNumber = 0): void
    {
        if ($lockNumber === 2) {
            $this->switchRelay(true, self::RELAY_SWITCHING_DURATION);
        } else {
            $lockNumber++;
            $this->apiCall("/api/v1/doors/$lockNumber/open", 'POST', null, 3);
        }
    }

    public function prepare(): void
    {
        parent::prepare();
        $this->setNetwork();
        $this->setRfidMode();
        $this->setDisplayLocalization();
        $this->setDisplayImage();
    }

    public function setAudioLevels(array $levels): void
    {
        if (count($levels) !== 5) {
            return;
        }

        $this->apiCall('/api/v1/configuration', 'PATCH', [
            'volume' => [
                'speaker' => $levels[0],
                'mic' => $levels[1],
                'sys' => $levels[2],
                'analog_speaker' => $levels[3],
                'analog_mic' => $levels[4],
            ],
        ]);
    }

    public function setCallTimeout(int $timeout): void
    {
        $this->apiCall('/api/v1/configuration', 'PATCH', ['commutator' => ['calltime' => $timeout]]);
    }

    public function setCmsModel(string $model = ''): void
    {
        $this->apiCall('/api/v1/configuration', 'PATCH', ['commutator' => self::CMS_PARAMS[$model] ?? []]);
    }

    public function setConciergeNumber(int $sipNumber): void
    {
        $this->loadDialplans();

        $this->dialplans['CONS'] = [
            'sip_number' => "$sipNumber",
            'analog' => false,
            'sip' => true,
            'map' => 0,
        ];
    }

    public function setDisplayText(array $textLines): void
    {
        $this->apiCall('/api/v1/configuration', 'PATCH', [
            'display' => [
                'labels' => [
                    $textLines[0] ?? '',
                    $textLines[1] ?? '',
                    $textLines[2] ?? '',
                ],
            ],
        ]);
    }

    public function setDtmfCodes(
        string $code1 = '1',
        string $code2 = '2',
        string $code3 = '3',
        string $codeCms = '1',
    ): void
    {
        $this->apiCall('/api/v1/configuration', 'PATCH', [
            'door' => [
                'dtmf_open_local' => [$code1, $code2],
                'dtmf_open_remote' => $codeCms,
            ],
        ]);
    }

    public function setFreePassEnabled(bool $enabled): void
    {
        $this->apiCall('/api/v1/configuration', 'PATCH', [
            'door' => [
                'unlock' => $enabled ? self::UNLOCK_DATE : '',
                'unlock2' => $enabled ? self::UNLOCK_DATE : '',
            ],
        ]);
    }

    public function setGateModeEnabled(bool $enabled): void
    {
        // TODO: need to set some CMS as default, otherwise there will be difference after disabling gate mode
        if ($enabled === false) {
            return;
        }

        $this->apiCall('/api/v1/configuration', 'PATCH', [
            'commutator' => [
                'type' => 'GATE',
                'mode' => 1,
            ],
        ]);
    }

    public function setLanguage(string $language): void
    {
        $payload = [
            'action' => 'locale',
            'ui_language' => $language === 'ru' ? 'ru|Russian' : 'en|English',
        ];

        $this->apiCall('/cgi-bin/webui-settings.cgi', 'POST', $payload, multipart: true);
    }

    public function setPublicCode(int $code = 0): void
    {
        // Empty implementation
    }

    public function setSosNumber(int $sipNumber): void
    {
        $this->loadDialplans();

        $this->dialplans['SOS'] = [
            'sip_number' => "$sipNumber",
            'analog' => false,
            'sip' => true,
            'map' => 0,
        ];
    }

    public function setTalkTimeout(int $timeout): void
    {
        // Empty implementation
    }

    public function setUnlockTime(int $time = 3): void
    {
        $this->apiCall('/api/v1/configuration', 'PATCH', [
            'door' => [
                'open_time' => $time,
                'open_2_time' => $time,
            ],
        ]);
    }

    public function syncData(): void
    {
        $this->uploadDialplans();
        $this->uploadRfids();
        $this->setCmsRange();
    }

    public function transformDbConfig(array $dbConfig): array
    {
        $dbConfig = $this->commonTransformDbConfig($dbConfig);

        if ($dbConfig['cmsModel'] !== '') {
            $cmsType = self::CMS_PARAMS[$dbConfig['cmsModel']]['type'];
            $this->cmsModelName = $dbConfig['cmsModel'];
            if (in_array($cmsType, ['METAKOM', 'ELTIS', 'BEWARD_105_108'])) {
                $dbConfig['cmsModel'] = $cmsType;
            }

            $dbConfig['matrix'] = $this->remapMatrix($dbConfig['matrix'], $dbConfig['apartments']);
        }

        $dbConfig['cmsLevels'] = [];

        $dbConfig['sip']['stunEnabled'] = false;
        $dbConfig['sip']['stunServer'] = '';
        $dbConfig['sip']['stunPort'] = 3478;

        foreach ($dbConfig['apartments'] as &$apartment) {
            $apartment['cmsLevels'] = [];
        }

        return $dbConfig;
    }

    /**
     * Adds or updates a key (RFID, personal code, BLE).
     *
     * @param string $value Normalized key value.
     * @param int $flat The flat number associated with the key.
     * @param KeyType $type Enum representing the key type.
     * @return void
     */
    protected function addKey(string $value, int $flat, KeyType $type): void
    {
        $this->loadKeys();
        $this->keys[$value] = Key::buildKey($flat, $type);
    }

    /**
     * Removes personal codes of the specified flat.
     *
     * @param int $flatNumber Flat number.
     * @return void
     */
    protected function deleteFlatPersonalCodes(int $flatNumber): void
    {
        $this->loadKeys();

        $this->keys = array_filter(
            $this->keys,
            fn(string $data) => Key::buildKey($flatNumber, KeyType::CodePersonal) !== $data,
        );
    }

    protected function getApartments(): array
    {
        $this->loadDialplans();

        $flats = [];
        $personalCodes = $this->getPersonalCodes();

        foreach ($this->dialplans as $flatNumber => $dialplan) {
            if ($dialplan['sip'] === false || in_array($flatNumber, ['SOS', 'CONS', 'KALITKA', 'FRSI'])) {
                continue;
            }

            $flats[$flatNumber] = [
                'apartment' => $flatNumber,
                'code' => $personalCodes[$flatNumber] ?? 0,
                'sipNumbers' => [$dialplan['sip_number']],
                'cmsEnabled' => $dialplan['analog'],
                'cmsLevels' => [],
            ];
        }

        return $flats;
    }

    /** @return Generator<int, array> */
    protected function getApartmentsDialplans(bool $unmapped = false): Generator
    {
        foreach ($this->dialplans as $apartment => $dialplan) {
            if (ctype_digit((string)$apartment) && ($dialplan['map'] != 0 || $unmapped)) {
                yield (int)$apartment => $dialplan;
            }
        }
    }

    protected function getAudioLevels(): array
    {
        $volume = $this->apiCall('/api/v1/configuration')['volume'] ?? null;

        if (!is_array($volume)) {
            return [];
        }

        return array_values($volume);
    }

    protected function getCmsModel(): string
    {
        ['type' => $rawType, 'mode' => $mode] = $this->apiCall('/api/v1/configuration')['commutator'];

        return match ($rawType) {
            'DIGITAL' => 'QAD-100',
            'CYFRAL' => 'KMG-100',
            'FACTORIAL' => 'FACTORIAL 8x8',
            'BEWARD_100' => 'KKM-100S2',
            'VIZIT' => match ($mode) {
                2 => 'BK-100',
                3 => 'BK-400',
                default => $rawType,
            },
            default => $rawType,
        };
    }

    protected function getDtmfConfig(): array
    {
        $doorConfig = $this->apiCall('/api/v1/configuration')['door'];

        $dtmfLocal = $doorConfig['dtmf_open_local'] ?? ['1', '2'];
        $dtmfRemote = $doorConfig['dtmf_open_remote'] ?? '1';

        return [
            'code1' => $dtmfLocal[0] ?? '1',
            'code2' => $dtmfLocal[1] ?? '2',
            'code3' => '3',
            'codeCms' => $dtmfRemote,
        ];
    }

    protected function getMatrix(): array
    {
        $this->loadDialplans();

        $matrix = [];
        foreach ($this->getApartmentsDialplans() as $apartment => $dialplan) {
            if (!isset($this->dialplans[$apartment])) {
                continue;
            }

            $cell = self::getMatrixCell($dialplan['map'], $apartment);
            $matrix[$cell['index']] = $cell['value'];
        }

        return $matrix;
    }

    protected function getMatrixEdge(): ?int
    {
        return self::CMS_PARAMS[$this->cmsModelName]['edge'] ?? null;
    }

    /**
     * Returns all personal codes.
     *
     * @return int[] The list of personal codes.
     */
    protected function getPersonalCodes(): array
    {
        $this->loadKeys();

        $filtered = Key::filterByType($this->keys, KeyType::CodePersonal);
        $codes = [];

        foreach ($filtered as $code => $value) {
            $flatNumber = Key::getFlat($value);

            if ($flatNumber === null) {
                continue;
            }

            if (!isset($codes[$flatNumber])) {
                $codes[$flatNumber] = $code;
            } elseif ($codes[$flatNumber] !== $code) {
                // Set the personal code to null if the apartment has more than one personal code
                $codes[$flatNumber] = null;
            }
        }

        return $codes;
    }

    protected function getRfids(): array
    {
        $this->loadKeys();

        $rfidsRaw = array_keys(
            Key::filterByType($this->keys, KeyType::RfidPersonal),
        );

        return array_map(
            fn(string $rfid) => str_pad(strtoupper($rfid), 14, '0', STR_PAD_LEFT),
            $rfidsRaw,
        );
    }

    protected function getSipConfig(): array
    {
        [
            'domain' => $domain,
            'user' => $user,
            'password' => $password,
        ] = $this->apiCall('/api/v1/configuration')['sip'];

        [$server, $port] = explode(':', $domain, 2);

        return [
            'server' => $server,
            'port' => $port,
            'login' => $user,
            'password' => $password,
            'stunEnabled' => false,
            'stunServer' => '',
            'stunPort' => 3478,
        ];
    }

    /**
     * Load and cache dialplans from the API if they haven't been loaded already.
     *
     * @return void
     */
    protected function loadDialplans(): void
    {
        if ($this->dialplans === null) {
            $this->dialplans = $this->apiCall('/api/v1/apartments') ?? [];
        }
    }

    /**
     * Load and cache keys (RFID, personal code, BLE) from the API if they haven't been loaded already.
     *
     * @return void
     */
    protected function loadKeys(): void
    {
        if ($this->keys === null) {
            $this->keys = $this->apiCall('/api/v1/rfids') ?? [];
        }
    }

    protected function remapMatrix(array $matrix, array $configApartments = []): array
    {
        $this->loadDialplans();

        $newMatrix = [];
        $edge = $this->getMatrixEdge();
        foreach ($matrix as $index => $cell) {
            $apartment = $cell['apartment'];
            if (!isset($this->dialplans[$apartment]) && !isset($configApartments[$apartment])) {
                continue;
            }

            $mapping = $cell['hundreds'] * 100 + $cell['tens'] * 10 + $cell['units'];
            if ($edge && $mapping % $edge !== 0) {
                $newMatrix[$index] = $cell;
                continue;
            }

            $newCell = self::getMatrixCell($mapping + $edge, $apartment);
            $newMatrix[$newCell['index']] = $newCell['value'];
        }

        return $newMatrix;
    }

    /**
     * Set CMS range based on apartment numbers.
     *
     * @return void
     */
    protected function setCmsRange(): void
    {
        $apartmentNumbers = array_keys($this->getApartments());

        $minApartmentNumber = $apartmentNumbers ? min($apartmentNumbers) : 0;
        $maxApartmentNumber = $apartmentNumbers ? max($apartmentNumbers) : 0;

        $params = [
            'ap_min' => $minApartmentNumber,
            'ap_max' => $maxApartmentNumber,
        ];

        // Set cross numbering mode for CMS if device is not in gate mode
        if ($this->isGateModeEnabled() === false && $this->getCmsModel() !== 'BK-400') {
            $isCrossNumbering = $minApartmentNumber !== $maxApartmentNumber &&
                intdiv($minApartmentNumber, 100) !== intdiv($maxApartmentNumber - 1, 100);

            $params['mode'] = $isCrossNumbering ? 2 : 1;
        }

        $this->apiCall('/api/v1/configuration', 'PATCH', ['commutator' => $params]);
    }

    /**
     * Upload and set display image.
     *
     * @param string|null $pathToImage (Optional) Path to the image which will be uploaded.
     * If null, the default path will be used.
     * @return void
     */
    protected function setDisplayImage(?string $pathToImage = null): void
    {
        if ($pathToImage === null) {
            $pathToImage = __DIR__ . '/assets/display_image.jpg';
        }

        if (!file_exists($pathToImage) || !is_file($pathToImage)) {
            return;
        }

        sleep(15); // Yes...
        $this->apiCall('/api/v1/file', 'POST', ['IMAGE' => new CURLFile($pathToImage)]);
    }

    /**
     * Sets the text for display messages.
     *
     * @return void
     */
    protected function setDisplayLocalization(): void
    {
        $localization = require __DIR__ . '/config/display_localization.php';
        $this->apiCall('/api/v1/configuration', 'PATCH', ['display' => ['localization' => $localization]]);
    }

    /**
     * Set network params.
     *
     * @return void
     */
    protected function setNetwork(): void
    {
        $this->apiCall('/cgi-bin/configManager.cgi', 'GET', [
            'action' => 'setConfig',
            'RTSP.Block' => 'false',
            'Agent.Enable' => 'false',
        ]);
    }

    /**
     * Set RFID reader mode.
     *
     * @return void
     */
    protected function setRfidMode(): void
    {
        $this->apiCall('/api/v1/configuration', 'PATCH', ['door' => ['rfid_pass_en' => false]]);
    }

    /**
     * Switches the internal relay.
     *
     * @param bool $isOn True to turn the relay on, false to turn it off.
     * @param int $duration Duration in seconds the relay should stay on before switching off automatically.
     * Use 0 to keep the state until explicitly changed.
     * @return void
     */
    protected function switchRelay(bool $isOn, int $duration = 0): void
    {
        $this->apiCall('/api/v1/relay/' . ($isOn ? 'on' : 'off'), 'POST', ['duration' => $duration], 3);
    }

    /**
     * Upload the dialplan from the cache into the intercom.
     *
     * @return void
     */
    protected function uploadDialplans(): void
    {
        if ($this->dialplans !== null) {
            $this->apiCall('/api/v1/apartments', 'PUT', $this->dialplans);
        }
    }

    /**
     * Upload RFID codes from the cache into the intercom.
     *
     * @return void
     */
    protected function uploadRfids(): void
    {
        if ($this->keys !== null) {
            $this->apiCall('/api/v1/rfids', 'PUT', $this->keys);
        }
    }
}
