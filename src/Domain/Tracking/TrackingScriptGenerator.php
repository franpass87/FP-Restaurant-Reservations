<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tracking;

/**
 * Genera gli script JavaScript per il tracking client-side.
 * Estratto da Manager per migliorare la manutenibilità.
 */
final class TrackingScriptGenerator
{
    /**
     * Genera lo script di bootstrap per il tracking client-side.
     *
     * @param string $jsonConfig Configurazione in formato JSON
     */
    public function generateBootstrap(string $jsonConfig): string
    {
        $script = <<<JS
(function(w,d){
    var cfg = $jsonConfig;
    w.fpResvTracking = w.fpResvTracking || {};
    var api = w.fpResvTracking;
    api.config = cfg;
    api.debug = !!cfg.debug;
    api.state = cfg.consent || {};
    api.dispatch = api.dispatch || function(){};
    api.getConsent = function(){return Object.assign({}, api.state);};
    api.log = function(){if(!api.debug){return;} if (typeof console !== 'undefined' && console.log) { console.log.apply(console, arguments); }};
    function normalize(value){
        if (typeof value === 'boolean'){return value ? 'granted':'denied';}
        if (typeof value === 'string'){var lower = value.toLowerCase(); return lower === 'granted' ? 'granted' : 'denied';}
        return 'denied';
    }
    function gtagConsent(){
        return {
            analytics_storage: api.state.analytics || 'denied',
            ad_storage: api.state.ads || 'denied',
            ad_user_data: api.state.ads || 'denied',
            ad_personalization: api.state.ads || 'denied',
            personalization_storage: api.state.personalization || 'denied',
            functionality_storage: 'granted',
            security_storage: 'granted'
        };
    }
    function ensureGtag(){
        w.dataLayer = w.dataLayer || [];
        if (typeof w.gtag === 'function'){return;}
        w.gtag = function(){w.dataLayer.push(arguments);};
        w.gtag('js', new Date());
        w.gtag('consent', 'default', cfg.gtagConsent || gtagConsent());
        if (cfg.ga4Id){ w.gtag('config', cfg.ga4Id, {send_page_view:false}); }
        if (cfg.googleAdsId){ w.gtag('config', cfg.googleAdsId); }
    }
    function loadMetaPixel(){
        if (!cfg.metaPixelId){return;}
        if (api.state.ads !== 'granted'){return;}
        if (w.fbq){ w.fbq('consent', 'grant'); return; }
        var n = function(){n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);};
        if (!w._fbq){ w._fbq = n; }
        n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
        var s = d.createElement('script'); s.async = true; s.src = 'https://connect.facebook.net/en_US/fbevents.js';
        var f = d.getElementsByTagName('script')[0]; f.parentNode.insertBefore(s,f);
        w.fbq = n; w.fbq('init', cfg.metaPixelId); w.fbq('consent', 'grant');
    }
    function loadClarity(){
        if (!cfg.clarityId){return;}
        if (api.state.analytics !== 'granted' || api.state.clarity !== 'granted'){return;}
        if (w.clarity){return;}
        (function(c,l,a,r,i,t,y){
            c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments);};
            t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window,document,'clarity','script',cfg.clarityId);
    }
    api.updateConsent = function(updates){
        updates = updates || {};
        var changed = false;
        ['analytics','ads','personalization','clarity'].forEach(function(key){
            if (!(key in updates)){return;}
            var normalized = normalize(updates[key]);
            if (api.state[key] !== normalized){
                api.state[key] = normalized;
                changed = true;
            }
        });
        api.state.functionality = 'granted';
        api.state.security = 'granted';
        if (!changed){return api.state;}
        ensureGtag();
        if (typeof w.gtag === 'function'){ w.gtag('consent','update', gtagConsent()); }
        if (typeof w.fbq === 'function'){ w.fbq('consent', api.state.ads === 'granted' ? 'grant' : 'revoke'); }
        loadMetaPixel();
        loadClarity();
        api.saveConsent();
        if (typeof w.CustomEvent === 'function'){ w.dispatchEvent(new CustomEvent('fp-resv-consent-change',{detail:api.state})); }
        return api.state;
    };
    api.saveConsent = function(){
        var ttl = parseInt(cfg.cookieTtl, 10) || 0;
        var expires = '';
        if (ttl > 0){
            var date = new Date();
            date.setTime(date.getTime() + ttl * 24 * 60 * 60 * 1000);
            expires = '; expires=' + date.toUTCString();
        }
        var secure = location.protocol === 'https:' ? '; secure' : '';
        document.cookie = cfg.cookieName + '=' + encodeURIComponent(JSON.stringify(api.state)) + expires + '; path=/; samesite=Lax' + secure;
    };
    api.dispatch = function(evt){
        if (!evt || typeof evt !== 'object'){return;}
        if (api.debug){ api.log('FP Resv event', evt); }
        ensureGtag();
        if (evt.ga4 && evt.ga4.name && typeof w.gtag === 'function'){
            w.gtag('event', evt.ga4.name, evt.ga4.params || {});
        }
        if (evt.ads && evt.ads.name && typeof w.gtag === 'function' && evt.ads.params){
            var adsParams = evt.ads.params;
            if (cfg.googleAdsSendTo && !adsParams.send_to){
                adsParams = Object.assign({}, adsParams, { send_to: cfg.googleAdsSendTo });
            }
            w.gtag('event', evt.ads.name, adsParams);
        }
        if (evt.meta && evt.meta.name && typeof w.fbq === 'function'){
            var metaParams = evt.meta.params || {};
            var metaOptions = {};
            if (evt.event_id || evt.meta.event_id){
                metaOptions.eventID = evt.event_id || evt.meta.event_id;
            }
            w.fbq('track', evt.meta.name, metaParams, metaOptions);
        }
    };
    api.pushEvent = function(name, payload){
        if (!name){return;}
        var event = Object.assign({event:name}, payload || {});
        w.dataLayer = w.dataLayer || [];
        w.dataLayer.push(event);
        api.dispatch(event);
        return event;
    };
    ensureGtag();
    if (api.state.ads === 'granted'){ loadMetaPixel(); }
    if (api.state.analytics === 'granted' && api.state.clarity === 'granted'){ loadClarity(); }
})(window, document);
JS;

        return $script;
    }

    /**
     * Genera lo script per il dispatch degli eventi in coda.
     *
     * @param string $jsonEvents Eventi in formato JSON
     */
    public function generateEventDispatcher(string $jsonEvents): string
    {
        return sprintf(
            '(function(w){w.dataLayer=w.dataLayer||[];var e=%s;if(!w.fpResvTracking){w.fpResvTracking={};}if(typeof w.fpResvTracking.dispatch!=="function"){w.fpResvTracking.dispatch=function(){return null;};}for(var i=0;i<e.length;i++){w.dataLayer.push(e[i]);w.fpResvTracking.dispatch(e[i]);}})(window);',
            $jsonEvents
        );
    }
}















