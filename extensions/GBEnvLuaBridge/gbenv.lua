if not mw.ext then
    mw.ext = {}
end

local gbenv = {}
local php = mw_interface

function gbenv.getApiKey()
    return php.getApiKey()
end

mw.ext.gbenv = gbenv

return gbenv