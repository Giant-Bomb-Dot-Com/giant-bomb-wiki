if not mw.ext then
    mw.ext = {}
end

local gbenv = {}
local php = mw_interface

function gbenv.getApiKey()
    return php.getApiKey()
end

function gbenv.fetchUserReviews( guid, offset, limit )
    return php.fetchUserReviews( guid, offset or 0, limit or 50 )
end

mw.ext.gbenv = gbenv

return gbenv