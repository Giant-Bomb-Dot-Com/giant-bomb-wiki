<?php

$cfg = require __DIR__ .
    "/../vendor/mediawiki/mediawiki-phan-config/src/config.php";

# Detect unused method parameters, etc.
$cfg["unused_variable_detection"] = true;

# Moderation doesn't keep all of its .php files under includes/ directory
# (something that should probably be fixed), so provide the proper paths here.

$cfg["file_list"][] = "ModerationLogFormatter.php";
$cfg["file_list"][] = "SpecialModeration.php";

$cfg["directory_list"] = array_merge($cfg["directory_list"], [
    "action",
    "api",
    "hooks",
    "lib",
    "plugins",
    "util",
]);

# Stubs for "detect unused code" mode
if (getopt("x", ["dead-code-detection"])) {
    $cfg["file_list"][] = ".phan/NotUnusedCode.php";
    $cfg["suppress_issue_types"][] = "PhanUnreferencedClass";
    $cfg["suppress_issue_types"][] = "PhanUnreferencedClosure";
    $cfg["suppress_issue_types"][] = "PhanReadOnlyPublicProperty";
    $cfg["suppress_issue_types"][] = "PhanReadOnlyProtectedProperty";
}

if (getenv("PHAN_CHECK_TESTSUITE")) {
    # Also check the ModerationTestsuite, but only if PHAN_CHECK_TESTSUITE=1 in the environment.
    # Shouldn't be included by default, because Phan assumes all present files to be included,
    # and we don't want it basing its assumptions on testsuite code when checking the main code.
    $cfg["directory_list"][] = "tests/phpunit";
    $cfg["directory_list"][] = "tests/common";
    $cfg["directory_list"][] = "tests/benchmarks";

    # PHPUnit classes, etc. Should be parsed, but not analyzed.
    $cfg["directory_list"][] = $IP . "/tests";
    $cfg["exclude_analysis_directory_list"][] = $IP . "/tests";

    # Testsuite only: don't emit "unused closure parameter" warnings for testsuite (for now?),
    # because almost every setTemporaryHook() needs @suppress.
    $cfg["suppress_issue_types"][] = "PhanUnusedClosureParameter";
}

# Exclude .mocked.*.php files (they are created by PHPUnit testsuite with CliEngine)
$cfg["exclude_file_regex"] = preg_replace(
    "/^@/",
    '@\/\..*\.mocked\.php$|',
    $cfg["exclude_file_regex"],
    1,
);

# Parse the soft dependencies (like MobileFrontend), but don't analyze them.
$cfg["directory_list"] = array_merge($cfg["directory_list"], [
    "../../extensions/AbuseFilter",
    "../../extensions/CheckUser",
    "../../extensions/MobileFrontend",
    "../../extensions/VisualEditor",
]);

$cfg["exclude_analysis_directory_list"] = array_merge(
    $cfg["exclude_analysis_directory_list"],
    [
        "../../extensions/AbuseFilter",
        "../../extensions/CheckUser",
        "../../extensions/MobileFrontend",
        "../../extensions/VisualEditor",
    ],
);

# Temporarily suppressed warnings.

if (getenv("PHAN_CHECK_DEPRECATED")) {
    # Warn about the use of @deprecated methods, etc.
    # Not enabled by default (without PHAN_CHECK_DEPRECATED=1) for backward compatibility.
    # (e.g. while we support MediaWiki 1.31, then warnings about something being deprecated in 1.34
    # shouldn't cause the Travis builds to fail).
    $cfg["suppress_issue_types"] = array_filter(
        $cfg["suppress_issue_types"],
        static function ($issue) {
            return strpos($issue, "PhanDeprecated") === false;
        },
    );
}

return $cfg;
