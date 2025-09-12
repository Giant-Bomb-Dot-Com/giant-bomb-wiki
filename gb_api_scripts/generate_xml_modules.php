<?php

require_once(__DIR__.'/libs/common.php');

class GenerateXMLModules extends Maintenance
{
    use CommonVariablesAndMethods;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Generates XML for modules");
    }

    public function execute()
    {
        $data = [
            [
                'title' => 'Module:Identifiers',
                'namespace' => $this->namespaces['module'],
                'description' => <<<MARKUP
-- Module:Idenfitiers
local p = {}

-- Match one or more characters that are not alphanumeric or underscore
local disallowed_pattern_run = "[^%w_]+"

function p.sanitize(text)
    -- Replace disallowed characters with an underscore
    local sanitized = mw.ustring.gsub(text, disallowed_pattern_run, "_")
    
    -- Remove leading and trailing underscores
    sanitized = mw.ustring.gsub(sanitized, "^_", "")
    sanitized = mw.ustring.gsub(sanitized, "_$", "")
    
    -- Truncate string to 150 chars leaving 105 chars for more text
    local limit = 150
    if mw.ustring.len(sanitized) > limit then
        sanitized = mw.ustring.sub(sanitized, 1, limit)
    end
    
    return sanitized
end

local function isNotEmpty(arg_value)
    -- First check if it's not nil, then if it's not an empty string after trimming
    return arg_value ~= nil and arg_value ~= ''
end

function p.getReleaseIdentifier(frame)
    local args = frame.args

    local name = args.Name or ""
    local region = args.Region or ""
    local platform = args.Platform or ""

    local sanitized_name = p.sanitize(name)
    local sanitized_region = p.sanitize(region)
    local sanitized_platform = p.sanitize(mw.ustring.gsub(platform, ".*%/", ""))

    return "Release_" .. sanitized_name .. "_" .. sanitized_region .. "_" .. sanitized_platform
end

function p.getDlcIdentifier(frame)
    local args = frame.args

    local name = args.Name or ""
    local platform = args.Platform or ""

    local sanitized_name = p.sanitize(name)
    local sanitized_platform = p.sanitize(mw.ustring.gsub(platform, ".*%/", ""))

    return "DLC_" .. sanitized_name .. "_" .. sanitized_platform
end

function p.getCreditIdentifier(frame)
    local args = frame.args

    local main_id
    if isNotEmpty(args.Game) then
        local last_slash_pos = mw.ustring.find(args.Game, "/", nil, true)
        if last_slash_pos then
            main_id = mw.ustring.sub(args.Game, last_slash_pos + 1)
        else
            main_id = args.Game
        end
    elseif isNotEmpty(args.Release) then
        local last_hash_pos = mw.ustring.find(args.Release, "#", nil, true)
        if last_hash_pos then
            main_id = mw.ustring.sub(args.Release, last_hash_pos + 1)
        else
            main_id = args.Release
        end
        main_id = p.sanitize(main_id)
    elseif isNotEmpty(args.Dlc) then
        local last_hash_pos = mw.ustring.find(args.Dlc, "#", nil, true)
        if last_hash_pos then
            main_id = mw.ustring.sub(args.Dlc, last_hash_pos + 1)
        else
            main_id = args.Dlc
        end
        main_id = p.sanitize(main_id)
    else
        main_id = "Unknown"
    end

    local sanitized_person = p.sanitize(mw.ustring.gsub(args.Person, ".*%/", ""))
    
    local santized_department = p.sanitize(args.Department)
    
    local sanitized_role
    if isNotEmpty(args.Role) then
        sanitized_role = p.sanitize(args.Role)
    else
        sanitized_role = "Unknown"
    end

    return "Credit_" .. main_id .. "_" .. sanitized_person .. "_" .. santized_department .. "_" .. sanitized_role
end

return p
MARKUP,
            ],
        ];

        $this->createXML('modules.xml', $data);
    }
}

$maintClass = GenerateXMLModules::class;

require_once RUN_MAINTENANCE_IF_MAIN; 