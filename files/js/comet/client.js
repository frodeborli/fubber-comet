
var FubberComet = function() {
    if(!window.JSON) return false;

    var scriptKey = 0;

    /**
    *   Constructor
    */
    function FubberComet(baseUrl) {
        this.baseUrl = baseUrl;
        this.channels = {};
        this.lastId = 0;
        this.eventListeners = {}
    }

    function createXhr() {
        if("withCredentials" in new XMLHttpRequest())
            return new XMLHttpRequest();
        else if(window.XDomainRequest)
            return new window.XDomainRequest();
        else
            return false;
    }

    function fallbackReconnect() {
        console.log("Unsupported!");
    }

    function reconnect(fc) {
        console.log("reconnect");

        if(fc.xhr) fc.xhr.abort();

        fc.xhr = createXhr();
        if(!fc.xhr) return fallbackReconnect();

        fc.xhr.open("get", fc.getSubscriptionUrl());

        fc.xhr.timeout = 60000;

        fc.xhr.ontimeout = function() {
            console.log("timeout");
            reconnect(fc);
        }
        fc.xhr.onload = function() {
            console.log("load");
            var rows = JSON.parse(fc.xhr.responseText);
            for(var i = 0; i < rows.length; i++)
                fc.receiveMessage(rows[i]);
            reconnect(fc);
        }
        fc.xhr.onerror = function() {
            console.log("error");
            reconnect(fc);
        }
        r = fc.xhr.send();
    }

    FubberComet.prototype.sendMessage = function(channels, message, callback) {
        var xhr = createXhr();
        xhr.open('post', this.getPushUrl());
        var formData = '';
        for(var i = 0; i < channels.length; i++)
            formData += 'c[]=' + encodeURIComponent(channels[i]) + '&';

        formData += 'p=' + encodeURIComponent(JSON.stringify(message));
        xhr.onerror = function() {
            if (callback) callback(false);
        }
        xhr.onload = function() {
            if (callback) callback(JSON.parse(xhr.responseText));
        }
        xhr.send(formData);
    }

    /**
    *   Public Methods
    */
    FubberComet.prototype.receiveMessage = function(message) {
        if(message.id > this.lastId) this.lastId = message.id;
        this.emit('message', { type: 'message', data: JSON.parse(message.payload) });
    }

    FubberComet.prototype.getPushUrl = function() {
        var url = this.baseUrl + '/ws/push';
        return url;
    }

    FubberComet.prototype.getSubscriptionUrl = function() {
        var url = this.baseUrl + '/ws/subscribe?';
        var first = true;
        for(var k in this.channels) if(this.channels.hasOwnProperty(k)) {
            url += (first ? '' : '&') + 'c[]=' + encodeURIComponent(k);
            first = false;
        }
        if(this.lastId !== 0) {
            url += '&lastId=' + this.lastId;
        }
        return url;
    }

    FubberComet.prototype.addEventListener = function(event, callback) {
        if(!this.eventListeners[event])
            this.eventListeners[event] = [];
        this.eventListeners[event].push(callback);
    }

    FubberComet.prototype.removeEventListener = function(event, callback) {
        if(!this.eventListeners[event])
            return;

        var newList = [];
        for(var i = 0; i < l; i++) {
            if(this.eventListeners[event][i] !== callback)
                newList.push(this.eventListeners[event][i]);
        }
        this.eventListeners[event][i] = newList;
    }

    FubberComet.prototype.emit = function(type, event) {
        console.log("Emitting", type, event);
        if(this.eventListeners[type]) {
            for(var i in this.eventListeners[type]) if(this.eventListeners[type].hasOwnProperty(i)) {
                this.eventListeners[type][i](event);
            }
        }
    }

    FubberComet.prototype.addChannel = function(channel) {
        this.channels[channel] = channel;
        reconnect(this);
    }

    FubberComet.prototype.removeChannel = function(channel) {
        delete this.channels[channel];
        reconnect(this);
    }

    return FubberComet;

}();
