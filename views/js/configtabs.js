(function (window) {
  'use strict';

  function extend(a, b) {
    for (var key in b) {
      if (b.hasOwnProperty(key)) {
        a[key] = b[key];
      }
    }
    return a;
  }

  function ConfigTabs(el, options) {
    this.el = el;
    this.options = extend({}, this.options);
    extend(this.options, options);
    this._init();
  }

  ConfigTabs.prototype.options = {
    start: 0
  };

  ConfigTabs.prototype._init = function () {
    // get current index
    this.index = Number(document.URL.substring(document.URL.indexOf("#mailchimp_tab_") + 15));
    // tabs elems
    this.tabs = [].slice.call(this.el.querySelectorAll('nav > a'));
    // content items
    this.items = [].slice.call(this.el.querySelectorAll('.content-wrap > section'));
    // set current
    this.current = -1;
    // current index
    this.options.start = (this.index != NaN ? Number(this.index) - 1 : 0);
    // show current content item
    this._show();
    // init events
    this._initEvents();
  };

  ConfigTabs.prototype._initEvents = function () {
    var self = this;
    this.tabs.forEach(function (tab, idx) {
      tab.addEventListener('click', function (ev) {
        self._show(idx);
      });
    });
  };

  ConfigTabs.prototype._show = function (idx) {
    if (this.current >= 0) {
      this.tabs[this.current].className = 'list-group-item';
      this.items[this.current].className = '';
    }
    // change current
    this.current = idx != undefined ? idx : this.options.start >= 0 && this.options.start < this.items.length ? this.options.start : 0;
    this.tabs[this.current].className = 'list-group-item tab-current active';
    this.items[this.current].className = 'content-current';
  };

  // add to global namespace
  window.ConfigTabs = ConfigTabs;
})(window);
