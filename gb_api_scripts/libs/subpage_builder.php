<?php

// builds /Releases and /DLC subpage wikitext from hydrated release/dlc arrays.
// shared by the migration xml path (game.php, escape=xml) and the repair
// generator (escape=api). xml mode entity-encodes for import files; api mode
// emits raw wikitext (only | needs escaping) since action=edit decodes nothing.

class SubpageBuilder
{
    const ESCAPE_XML = "xml";
    const ESCAPE_API = "api";

    // legacy release_date_type ints
    const DATE_FULL = 0;
    const DATE_MONTH = 1;
    const DATE_QUARTER = 2;
    const DATE_YEAR = 3;

    const DATE_TYPE_LABELS = [
        self::DATE_FULL => "Full",
        self::DATE_MONTH => "Month",
        self::DATE_QUARTER => "Quarter",
        self::DATE_YEAR => "Year",
    ];

    const MONTH_NAMES = [
        1 => "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December",
    ];

    const REGIONS = [
        1 => "United States",
        2 => "United Kingdom",
        6 => "Japan",
        11 => "Australia",
    ];

    const RATINGS = [
        1 => "Ratings/ESRB_T",
        2 => "Ratings/PEGI_16",
        5 => "Ratings/BBFC_15",
        6 => "Ratings/ESRB_E",
        7 => "Ratings/PEGI_3",
        9 => "Ratings/ESRB_K_A",
        12 => "Ratings/OFLC_MA15",
        13 => "Ratings/OFLC_M15",
        14 => "Ratings/OFLC_G",
        15 => "Ratings/OFLC_G8",
        16 => "Ratings/ESRB_M",
        17 => "Ratings/BBFC_18",
        18 => "Ratings/PEGI_7",
        19 => "Ratings/CERO_All_Ages",
        20 => "Ratings/BBFC_PG",
        21 => "Ratings/BBFC_12",
        23 => "Ratings/ESRB_AO",
        24 => "Ratings/CERO_18",
        25 => "Ratings/CERO_A",
        26 => "Ratings/ESRB_EC",
        27 => "Ratings/CERO_C",
        28 => "Ratings/CERO_15",
        29 => "Ratings/ESRB_E10",
        30 => "Ratings/BBFC_U",
        31 => "Ratings/OFLC_M",
        32 => "Ratings/CERO_D",
        33 => "Ratings/CERO_B",
        34 => "Ratings/CERO_Z",
        36 => "Ratings/PEGI_12",
        37 => "Ratings/PEGI_18",
        38 => "Ratings/OFLC_PG",
        39 => "Ratings/OFLC_R18",
    ];

    const RESOLUTIONS = [
        5 => "Resolutions/1080p",
        6 => "Resolutions/1080i",
        7 => "Resolutions/720p",
        8 => "Resolutions/480p",
        9 => "Resolutions/PC_CGA_320x200",
        10 => "Resolutions/PC_EGA_640x350",
        11 => "Resolutions/PC_VGA_640x480",
        12 => "Resolutions/PC_WVGA_768x480",
        13 => "Resolutions/PC_SVGA_800x600",
        14 => "Resolutions/PC_1024x768",
        15 => "Resolutions/PC_1440x900",
        16 => "Resolutions/PC_1600x1200",
        17 => "Resolutions/PC_2560x1440",
        18 => "Resolutions/PC_2560x1600",
        19 => "Resolutions/Other_PC_Resolution",
        20 => "Resolutions/Other_Console_Resolution",
    ];

    const SOUND_SYSTEMS = [
        4 => "Sound_Systems/Mono",
        5 => "Sound_Systems/Stereo",
        6 => "Sound_Systems/5.1",
        7 => "Sound_Systems/7.1",
        8 => "Sound_Systems/Dolby_Pro_Logic_II",
        9 => "Sound_Systems/DTS",
    ];

    // 8-14 single player, 15-25 multiplayer
    const FEATURES = [
        8 => "Single_Player_Features/Camera_support",
        9 => "Single_Player_Features/Voice_control",
        10 => "Single_Player_Features/Motion_control",
        11 => "Single_Player_Features/Driving_wheel_native",
        12 => "Single_Player_Features/Flightstick_native",
        13 => "Single_Player_Features/PC_gamepad_native",
        14 => "Single_Player_Features/Head_tracking_native",
        15 => "Multiplayer_Features/Local_co_op",
        16 => "Multiplayer_Features/LAN_co_op",
        17 => "Multiplayer_Features/Online_co_op",
        18 => "Multiplayer_Features/Local_competitive",
        19 => "Multiplayer_Features/LAN_competitive",
        20 => "Multiplayer_Features/Online_competitive",
        21 => "Multiplayer_Features/Local_splitscreen",
        22 => "Multiplayer_Features/Online_splitscreen",
        23 => "Multiplayer_Features/Pass_and_play",
        24 => "Multiplayer_Features/Voice_chat",
        25 => "Multiplayer_Features/Asynchronous_multiplayer",
    ];

    const PRODUCT_CODE_TYPES = [
        1 => "EAN/13",
        2 => "UPC/A",
        3 => "ISBN-10",
    ];

    const COMPANY_CODE_TYPES = [
        1 => "Nintendo Product ID",
        2 => "Sony Company Code",
    ];

    const MIN_YEAR = 1940;

    public static $rejectedDates = [];

    public static function escape(?string $v, string $mode): string
    {
        $v = trim((string) $v);
        if ($mode === self::ESCAPE_XML) {
            return htmlspecialchars($v, ENT_XML1, "UTF-8");
        }
        // api mode: a raw | would end the template param
        return str_replace("|", "&#124;", $v);
    }

    public static function imageUrlFromPathName(?string $path, ?string $name): string
    {
        if ($name === null || $name === "") {
            return "";
        }
        $file = str_replace([" ", "&"], ["%20", "%26"], $path . $name);
        return "https://giantbomb.com/a/uploads/original/" . $file;
    }

    public static function guessProductCodeType(?string $code, $typeId): string
    {
        if (empty($code)) {
            return "";
        }
        if (!empty($typeId) && isset(self::PRODUCT_CODE_TYPES[$typeId])) {
            return self::PRODUCT_CODE_TYPES[$typeId];
        }
        $code = trim($code);
        if (preg_match('/^(\d[ -]?){12}\d$/', $code)) {
            return self::PRODUCT_CODE_TYPES[1];
        }
        if (preg_match('/^(\d[ -]?){11}\d$/', $code)) {
            return self::PRODUCT_CODE_TYPES[2];
        }
        if (preg_match('/^(?:\d-?){9}[\dX]$/', $code)) {
            return self::PRODUCT_CODE_TYPES[3];
        }
        return "";
    }

    public static function widescreenLabel($val): string
    {
        if ($val === null || $val === "") {
            return "";
        }
        return ((int) $val) === 1 ? "Yes" : "No";
    }

    // legacy date + type int -> template params. full dates fill ReleaseDate,
    // fuzzy dates fill only their part fields (template composes iso from parts).
    // out-of-range years demote to None and land in self::$rejectedDates.
    public static function dateParams(?string $date, $typeId, $rowId = null): array
    {
        $out = [
            "ReleaseDate" => "",
            "ReleaseDateType" => "None",
            "MonthPart" => "",
            "MonthYearPart" => "",
            "QuarterPart" => "",
            "QuarterYearPart" => "",
            "YearPart" => "",
        ];

        if (empty($date) || $date === "0000-00-00") {
            return $out;
        }

        $parts = explode("-", $date);
        $year = (int) ($parts[0] ?? 0);
        $month = (int) ($parts[1] ?? 0);

        if ($year < self::MIN_YEAR || $year > (int) date("Y") + 5) {
            self::$rejectedDates[] = ["id" => $rowId, "date" => $date];
            return $out;
        }

        $typeId = $typeId === null ? self::DATE_FULL : (int) $typeId;

        switch ($typeId) {
            case self::DATE_MONTH:
                $out["ReleaseDateType"] = "Month";
                $out["MonthPart"] = self::MONTH_NAMES[max(1, min(12, $month))];
                $out["MonthYearPart"] = (string) $year;
                break;
            case self::DATE_QUARTER:
                $out["ReleaseDateType"] = "Quarter";
                $out["QuarterPart"] = "Q" . max(1, min(4, (int) ceil($month / 3)));
                $out["QuarterYearPart"] = (string) $year;
                break;
            case self::DATE_YEAR:
                $out["ReleaseDateType"] = "Year";
                $out["YearPart"] = (string) $year;
                break;
            default:
                $out["ReleaseDateType"] = "Full";
                $out["ReleaseDate"] = $date;
                break;
        }

        return $out;
    }

    // hydrated release shape: Guid, Name, Image, Region, Platform, Rating,
    // Developers[], Publishers[], date params, ProductCode(+Type),
    // CompanyCode(+Type), WidescreenSupport, Resolutions[], SoundSystems[],
    // SinglePlayerFeatures[], MultiplayerFeatures[], MinimumPlayers, MaximumPlayers
    public static function renderReleaseSubobject(array $r, string $gamePage, string $mode): string
    {
        $e = fn($v) => self::escape($v, $mode);

        return "{{ReleaseSubobject\n" .
            "|Game={$gamePage}\n" .
            "|Guid={$r["Guid"]}\n" .
            "|Name={$e($r["Name"])}\n" .
            "|Image={$r["Image"]}\n" .
            "|Region={$r["Region"]}\n" .
            "|Platform={$r["Platform"]}\n" .
            "|Rating={$r["Rating"]}\n" .
            "|Developers=" . implode(",", $r["Developers"]) . "\n" .
            "|Publishers=" . implode(",", $r["Publishers"]) . "\n" .
            "|ReleaseDate={$r["ReleaseDate"]}\n" .
            "|ReleaseDateType={$r["ReleaseDateType"]}\n" .
            "|MonthPart={$r["MonthPart"]}\n" .
            "|MonthYearPart={$r["MonthYearPart"]}\n" .
            "|QuarterPart={$r["QuarterPart"]}\n" .
            "|QuarterYearPart={$r["QuarterYearPart"]}\n" .
            "|YearPart={$r["YearPart"]}\n" .
            "|ProductCode={$e($r["ProductCode"])}\n" .
            "|ProductCodeType={$r["ProductCodeType"]}\n" .
            "|CompanyCode={$e($r["CompanyCode"])}\n" .
            "|CompanyCodeType={$r["CompanyCodeType"]}\n" .
            "|WidescreenSupport={$r["WidescreenSupport"]}\n" .
            "|Resolutions=" . implode(",", $r["Resolutions"]) . "\n" .
            "|SoundSystems=" . implode(",", $r["SoundSystems"]) . "\n" .
            "|SinglePlayerFeatures=" . implode(",", $r["SinglePlayerFeatures"]) . "\n" .
            "|MultiplayerFeatures=" . implode(",", $r["MultiplayerFeatures"]) . "\n" .
            "|MinimumPlayers={$r["MinimumPlayers"]}\n" .
            "|MaximumPlayers={$r["MaximumPlayers"]}\n" .
            "}}\n";
    }

    public static function renderReleasesPage(string $gamePage, array $releases, string $mode): string
    {
        $body = "{{Releases\n|ParentPage={$gamePage}\n}}\n";
        foreach ($releases as $r) {
            $body .= self::renderReleaseSubobject($r, $gamePage, $mode);
        }
        return $body;
    }

    // hydrated dlc shape: Guid, Name, Image, Deck, LaunchPrice, Platform,
    // Developers[], Publishers[], DlcTypes[], date params
    public static function renderDlcSubobject(array $d, string $gamePage, string $mode): string
    {
        $e = fn($v) => self::escape($v, $mode);

        return "{{DlcSubobject\n" .
            "|Game={$gamePage}\n" .
            "|Guid={$d["Guid"]}\n" .
            "|Name={$e($d["Name"])}\n" .
            "|Image={$d["Image"]}\n" .
            "|Deck={$e($d["Deck"])}\n" .
            "|LaunchPrice={$d["LaunchPrice"]}\n" .
            "|Platform={$d["Platform"]}\n" .
            "|Developers=" . implode(",", $d["Developers"]) . "\n" .
            "|Publishers=" . implode(",", $d["Publishers"]) . "\n" .
            "|ReleaseDate={$d["ReleaseDate"]}\n" .
            "|ReleaseDateType={$d["ReleaseDateType"]}\n" .
            "|MonthPart={$d["MonthPart"]}\n" .
            "|MonthYearPart={$d["MonthYearPart"]}\n" .
            "|QuarterPart={$d["QuarterPart"]}\n" .
            "|QuarterYearPart={$d["QuarterYearPart"]}\n" .
            "|YearPart={$d["YearPart"]}\n" .
            "|DlcTypes=" . implode(",", $d["DlcTypes"]) . "\n" .
            "}}\n";
    }

    public static function renderDlcPage(string $gamePage, array $dlcs, string $mode): string
    {
        $body = "{{DLC\n|ParentPage={$gamePage}\n}}\n";
        foreach ($dlcs as $d) {
            $body .= self::renderDlcSubobject($d, $gamePage, $mode);
        }
        return $body;
    }
}

?>
