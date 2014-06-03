/**
 * Smile tracker implementation
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_Tracker
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   OSL 3.0
 */
(function () {

    var stCookies = {};

    function _uuid() {
        
        var uuid = "", i, random;
        
        for (i = 0; i < 32; i++) {
            random = Math.random() * 16 | 0;
       
            if (i == 8 || i == 12 || i == 16 || i == 20) {
                uuid += "-"
            }
            
            uuid += (i == 12 ? 4 : (i == 16 ? (random & 3 | 8) : random)).toString(16);
        }
        
        return uuid;
    }
    
    function _decodeCookies() {
        // Decode cookies
        document.cookie.split('; ').map(function(value) {
            var parts = value.split("=");
            if (parts.length == 2) {
                stCookies[parts[0]] = parts[1];
            }
        });
    }
    
    function _getCookieTrackingId(cookieName, expiresAt) {
        
        var trackingId = false;
        
        if (stCookies[cookieName]) {
            trackingId = stCookies[cookieName];
        }
        
        if (!trackingId) {
            trackingId = _uuid(); 
        }
        
        document.cookie = cookieName + '=' + trackingId + "; expires=" + expiresAt.toUTCString();
        
        return trackingId;
    }
     
    
    SmileTracker = {
       
        init : function(config) {
            SmileTracker.config = config;
            BOOMR.init({
                beacon_url: config.beaconUrl,
                BW: { enabled: false, base_url: config.bwBaseUrl }
            });
            SmileTracker.initSession();
        },
        
        initSession : function() {
            _decodeCookies();
            var expireAt = new Date();
            var config = SmileTracker.config.sessionConfig;
            
            expireAt.setSeconds(expireAt.getSeconds() + config['visit_cookie_lifetime']);
            SmileTracker.addSessionVar('uid', _getCookieTrackingId(config['visit_cookie_name'], expireAt));
            
            expireAt.setDate(expireAt.getDate() + config['visitor_cookie_lifetime']);
            SmileTracker.addSessionVar('vid', _getCookieTrackingId(config['visitor_cookie_name'], expireAt));
            
            BOOMR.addVar('t', Math.round(new Date().getTime() / 1000));
        },
        
        addSessionVar : function (varName, value) {
            BOOMR.addVar(this.transformVarName(varName, 'session'), value);
        },
    
        addPageVar : function (varName, value) {
            
            BOOMR.addVar(this.transformVarName(varName , 'page'), value);
        },
        
        transformVarName : function(varName, prefix) {
            return prefix + "." + varName;
        },
        
        enableCheckoutTracking : function ()
        {
            Checkout.prototype.superGotoSection = Checkout.prototype.gotoSection;
            Checkout.prototype.gotoSection = function (section, reloadProgressBlock) {
                if (!this.timerRunning) {
                    BOOMR.plugins.RT.startTimer('t_done');
                    BOOMR.addVar('page.opc.server_loading', 0);
                }
                BOOMR.addVar('page[opc][step]', section);
                this.superGotoSection(section, reloadProgressBlock);
                BOOMR.plugins.RT.done();
                this.timerRunning = false;
                BOOMR.removeVar('page.opc.step');
                BOOMR.removeVar('page.opc.server_loading');
            }
            
            
            Checkout.prototype.superSetLoadWaiting = Checkout.prototype.setLoadWaiting;
            Checkout.prototype.setLoadWaiting = function(step, keepDisabled) {
                BOOMR.plugins.RT.startTimer('t_done');
                BOOMR.addVar('page.opc.server_loading', 1);
                BOOMR.plugins.RT.startTimer('p');
                this.timerRunning = true;
                this.superSetLoadWaiting(step, keepDisabled);
            }
        }
    }
})();