const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');
const source = readFileSync(path.join(__dirname, '../../bin/frontend/controls/basket/Button.js'), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));

function fixture(width, options = {}) {
    let definition;
    const events = {};
    const calls = {small: 0, order: 0, popup: 0, errors: []};
    const window = {location: '/current', setTimeout() {}};
    const basket = {ready: () => Promise.resolve(), addEvents() {}};
    const dependencies = {
        'qui/QUI': {getWindowSize: () => ({x: width}), addEvent() {}},
        'package/quiqqer/order/bin/frontend/Basket': basket,
        'package/quiqqer/order/bin/frontend/OrderProcessUrl': () => { calls.order++; return Promise.resolve('/order'); },
        'package/quiqqer/order/bin/frontend/controls/orderProcess/Window': function() { this.open = () => calls.popup++; }
    };
    vm.runInNewContext(source, {
        window,
        console: {error: error => calls.errors.push(error)},
        Class: function(options) { return options; },
        define: (name, names, factory) => { definition = factory(...names.map(name => dependencies[name] ?? {})); },
        require: (names, resolve) => resolve(...names.map(name => dependencies[name]))
    });
    const attributes = {...definition.options, ...options};
    const node = {set() {}, getElement: () => null, addEventListener: (name, callback) => { events[name] = callback; }};
    const instance = Object.assign({}, definition, {
        mayBeDisplayed: () => true,
        getElm: () => node,
        getAttribute: name => attributes[name],
        showSmallBasket: () => calls.small++,
        updateDisplay() {}
    });
    instance.$onImport();
    return {click: events.click, window, calls};
}

for (const width of [375, 768]) {
    test(`legacy consumers still open checkout at ${width}px`, async () => {
        const f = fixture(width, {action: 'openSmallBasket'});
        f.click(); await flush();
        assert.equal(f.window.location, '/order');
        assert.equal(f.calls.small, 0);
    });
}

for (const [action, count] of [['openSmallBasket', 'small'], ['openOrderProcess', 'popup']]) {
    test(`desktop preserves ${action}`, async () => {
        const f = fixture(769, {action});
        f.click(); await flush();
        assert.equal(f.calls[count], 1);
        assert.equal(f.window.location, '/current');
    });
}

for (const width of [375, 1024]) {
    test(`configured checkout uses the page resolver at ${width}px`, async () => {
        const f = fixture(width, {action: 'openOrderProcessUrl', mobileAction: 'openOrderProcessUrl',
            getNavigationUrl: () => Promise.resolve('/simple')});
        f.click(); await flush();
        assert.equal(f.window.location, '/simple');
        assert.equal(f.calls.order, 0);
        assert.deepEqual(f.calls.errors, []);
    });
}

test('missing configured checkout does not invoke the legacy order resolver', async () => {
    const f = fixture(375, {mobileAction: 'openOrderProcessUrl', getNavigationUrl: () => Promise.resolve(null)});
    f.click(); await flush();
    assert.equal(f.window.location, '/current');
    assert.equal(f.calls.order, 0);
});

test('popup actions do not invoke the configured page resolver', async () => {
    const f = fixture(1024, {action: 'openOrderProcess',
        getNavigationUrl: () => { throw new Error('must not be called'); }});
    f.click(); await flush();
    assert.equal(f.calls.popup, 1);
    assert.deepEqual(f.calls.errors, []);
});

for (const width of [375, 1024]) {
    test(`cart action resolves on ${width}px and ignores parallel clicks`, async () => {
        let resolve, calls = 0;
        const f = fixture(width, {action: 'openShoppingCart', mobileAction: 'openShoppingCart',
            getNavigationUrl: mobile => {
                calls++;
                assert.equal(mobile, width <= 768);
                return new Promise(done => { resolve = done; });
            }});
        f.click(); f.click(); await flush();
        assert.equal(calls, 1);
        resolve('/cart'); await flush();
        assert.equal(f.window.location, '/cart');
    });
}

test('null destination keeps the page usable and allows another attempt', async () => {
    let calls = 0;
    const f = fixture(375, {mobileAction: 'openShoppingCart', getNavigationUrl: () => { calls++; return null; }});
    f.click(); await flush(); f.click(); await flush();
    assert.equal(calls, 2);
    assert.equal(f.window.location, '/current');
    assert.deepEqual(f.calls.errors, []);
});

test('resolver rejection is caught and does not navigate', async () => {
    const f = fixture(375, {mobileAction: 'openShoppingCart', getNavigationUrl: () => Promise.reject(new Error('failed'))});
    f.click(); await flush();
    assert.equal(f.window.location, '/current');
    assert.equal(f.calls.errors.length, 1);
});
