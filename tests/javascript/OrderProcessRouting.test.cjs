const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

async function loadCheckout(base = '/checkout', pathname = base) {
    const location = new URL(pathname, 'https://checkout.example.test');
    const warnings = [];
    const history = {
        pushState(state, title, url) { location.href = new URL(url, location).href; },
        replaceState(state, title, url) { location.href = new URL(url, location).href; }
    };
    const window = {location, history, addEventListener() {}, removeEventListener() {}};
    const document = {querySelectorAll: () => []};
    const QUI = {
        Storage: {get: () => false},
        Controls: {getControlsInElement: () => ({each() {}})},
        fireEvent() {}
    };
    const Ajax = {
        get(endpoint, callback, options) {
            let step = 'CustomerData';
            if (options.step) {
                step = options.step;
            } else if (endpoint.endsWith('getPrevious') || endpoint.endsWith('reload')) {
                step = 'Basket';
            } else if (endpoint.endsWith('send')) {
                step = 'Finish';
            }
            callback({url: base + '/' + step});
        }
    };
    let Navigo, definition, router;
    const amdDefine = (name, dependencies, factory) => {
        if (name === 'Navigo') {
            Navigo = factory();
            return;
        }
        function TrackNavigo(...args) {
            router = new Navigo(...args);
            return router;
        }
        definition = factory(
            QUI, {}, {}, {getFormData: () => ({})},
            {ready: () => Promise.resolve(), refresh() {}}, {}, Ajax, {}, TrackNavigo
        );
    };
    amdDefine.amd = true;
    const amdRequire = {config() {}};
    const context = vm.createContext({
        window, document, location, history,
        console: {warn: message => warnings.push(message), error: message => { throw new Error(message); }},
        setTimeout, clearTimeout,
        URL_OPT_DIR: '/packages/',
        define: amdDefine,
        require: amdRequire,
        Class: function (definition) { return definition; }
    });
    vm.runInContext(fs.readFileSync(process.argv[2], 'utf8'), context);
    vm.runInContext(
        fs.readFileSync(path.join(__dirname, '../../bin/frontend/controls/OrderProcess.js'), 'utf8'),
        context
    );
    const form = {get: () => false, addEvent() {}};
    const container = {getFirst: () => null, getElement: () => null, setStyle() {}};
    const element = {
        get: name => name === 'data-url' ? base : false,
        getElement: selector => {
            if (selector === '[name="order"]') return form;
            if (selector === '.quiqqer-order-ordering-nobody') return null;
            return container;
        },
        addClass() {}
    };
    const control = Object.assign({}, definition, {
        getElm: () => element,
        Loader: {setAttribute() {}, setStyles() {}},
        getAttribute: () => false,
        setAttribute() {},
        fireEvent() {},
        $startLoginCheck() {},
        $readButtonTexts() {},
        $refreshButtonEvents() {},
        $getCount: () => 2,
        validateStep: () => true,
        getCurrentStepData: () => ({step: 'Basket'}),
        $beginResultRendering() {},
        $endResultRendering() {},
        saveCurrentStep: () => Promise.resolve(),
        $renderResult: () => Promise.resolve()
    });
    await control.$onImport();

    return {control, location, warnings, close: () => router?.destroy()};
}

test('initializes pathname routing without deprecated constructor warnings', async () => {
    const checkout = await loadCheckout();
    try {
        assert.deepEqual(checkout.warnings, []);
        assert.equal(checkout.location.pathname, '/checkout');
        assert.equal(checkout.location.hash, '');
    } finally {
        checkout.close();
    }
});

for (const [method, expected] of [['next', 'CustomerData'], ['previous', 'Basket']]) {
    test(`${method} changes the checkout path without adding a hash`, async () => {
        const checkout = await loadCheckout();
        try {
            await checkout.control[method]();
            assert.equal(checkout.location.pathname, '/checkout/' + expected);
            assert.equal(checkout.location.hash, '');
        } finally {
            checkout.close();
        }
    });
}

for (const [method, args, expected] of [
    ['openStep', ['Payment'], 'Payment'],
    ['send', [], 'Finish'],
    ['reload', [], 'Basket']
]) {
    test(`${method} preserves pathname navigation`, async () => {
        const checkout = await loadCheckout();
        try {
            await checkout.control[method](...args);
            assert.equal(checkout.location.pathname, '/checkout/' + expected);
            assert.equal(checkout.location.hash, '');
        } finally {
            checkout.close();
        }
    });
}

test('preserves the checkout directory when navigating to the next step', async () => {
    const checkout = await loadCheckout('/shop/checkout');
    try {
        await checkout.control.next();
        assert.equal(checkout.location.pathname, '/shop/checkout/CustomerData');
        assert.equal(checkout.location.hash, '');
    } finally {
        checkout.close();
    }
});

test('an embedded checkout does not replace an unrelated page URL', async () => {
    const checkout = await loadCheckout('/checkout', '/profile');
    try {
        await checkout.control.next();
        assert.equal(checkout.location.pathname, '/profile');
        assert.equal(checkout.location.hash, '');
    } finally {
        checkout.close();
    }
});
