const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const source = readFileSync(path.join(root, 'bin/frontend/controls/OrderProcess.js'), 'utf8');
const locales = {
    next: 'ordering.btn.next',
    previous: 'ordering.btn.previous',
    backToShop: 'ordering.btn.backToShop',
    changePayment: 'ordering.btn.changePayment',
    payToOrder: 'ordering.btn.pay.to.order'
};

function fixture({texts = false, language = 'de', imported = 'false'} = {}) {
    let definition;
    const localeSource = readFileSync(path.join(root, `locale/${language}.xml`), 'utf8');
    function translate(group, key) {
        const block = localeSource.split(`<locale name="${key}"`)[1]?.split('</locale>')[0];
        return block?.match(/<!\[CDATA\[([\s\S]*?)\]\]>/)?.[1].trim() ?? key;
    }
    const collection = {
        removeEvents() {}, addEvent() {}, getParent: () => []
    };
    let buttons = {};
    const container = {
        getAttribute: () => imported,
        querySelector(selector) { return buttons[selector.match(/data-name="([^"]+)"/)[1]] ?? null; },
        getElements: () => collection,
        set(name, names) { this.replace(names); },
        replace(names = Object.keys(locales)) {
            buttons = Object.fromEntries(names.map(name => [name, {textContent: 'Server text'}]));
        }
    };
    container.replace();
    const dependencies = {
        Locale: {get: translate},
        'qui/QUI': {
            fireEvent() {},
            parse: () => Promise.resolve(),
            Controls: {getControlsInElement: () => ({each() {}})}
        }
    };
    const requireStub = () => {};
    requireStub.config = () => {};
    vm.runInNewContext(source, {
        URL_OPT_DIR: '/', require: requireStub,
        Class: function (options) { return options; },
        Element: function (tag, options) {
            if (options.html && options.html.buttons) {
                const payload = options.html;
                return {
                    getElement(selector) {
                        if (selector === '.quiqqer-order-ordering-step') {
                            return {get: () => 'New step'};
                        }
                        if (selector === '.quiqqer-order-ordering-buttons') {
                            return {get: () => payload.buttons};
                        }
                        return null;
                    },
                    getElements: () => []
                };
            }
            return {inject() {}, getSize: () => ({y: 100}), getComputedSize: () => ({totalHeight: 100})};
        },
        define(name, names, factory) {
            definition = factory(...names.map(name => dependencies[name] ?? {}));
        }
    });
    const attributes = {buttonTexts: texts, current: 'Basket'};
    const instance = Object.assign({}, definition, {
        getAttribute: name => attributes[name],
        setAttribute: (name, value) => { attributes[name] = value; },
        $Buttons: container,
        $TimelineContainer: {getElements: () => collection, getElement: () => null},
        $StepContainer: {},
        $refreshSteps() {},
        $animate: () => Promise.resolve(),
        $parseProcessingPaymentChange: () => Promise.resolve(),
        resize: () => Promise.resolve(),
        fireEvent() {},
        Loader: {hide() {}}
    });
    return {instance, container, attributes, translate, button: name => buttons[name]};
}

for (const language of ['de', 'en', 'es', 'fr', 'it', 'pl', 'pt']) {
    test(`unset button texts preserve all existing ${language} labels`, () => {
        const f = fixture({language});
        f.instance.$applyButtonTexts();
        for (const [name, key] of Object.entries(locales)) {
            assert.equal(f.button(name).textContent, f.translate('quiqqer/order', key));
        }
    });
}

for (const texts of [null, {}, [], 'Continue', 123, {next: '', steps: {Basket: {next: ' '}}},
    {next: {}, steps: {Basket: {next: false}}}, {steps: 'Basket'}, {steps: {Basket: []}}]) {
    test(`invalid or empty options use locale defaults (${JSON.stringify(texts)})`, () => {
        const f = fixture({texts});
        f.instance.$applyButtonTexts();
        for (const [name, key] of Object.entries(locales)) {
            assert.equal(f.button(name).textContent, f.translate('quiqqer/order', key));
        }
    });
}

test('step text overrides general text, while unrelated buttons keep their locales', () => {
    const f = fixture({texts: {
        next: 'Continue',
        steps: {Basket: {next: 'To customer data'}, CustomerData: {previous: 'Back to basket'}}
    }});
    f.instance.$applyButtonTexts();
    assert.equal(f.button('next').textContent, 'To customer data');
    f.attributes.current = 'CustomerData';
    f.instance.$applyButtonTexts();
    assert.equal(f.button('next').textContent, 'Continue');
    assert.equal(f.button('previous').textContent, 'Back to basket');
    assert.equal(f.button('payToOrder').textContent, f.translate('quiqqer/order', locales.payToOrder));
});

test('all standard button texts can be overridden independently', () => {
    const texts = Object.fromEntries(Object.keys(locales).map(name => [name, `Custom ${name}`]));
    const f = fixture({texts});
    f.instance.$applyButtonTexts();
    for (const name of Object.keys(locales)) {
        assert.equal(f.button(name).textContent, texts[name]);
    }
});

test('a blank step text falls back to the general text and removing overrides restores locales', () => {
    const f = fixture({texts: {next: 'Continue', steps: {Basket: {next: ''}}}});
    f.instance.$applyButtonTexts();
    assert.equal(f.button('next').textContent, 'Continue');
    f.attributes.buttonTexts = false;
    f.instance.$applyButtonTexts();
    assert.equal(f.button('next').textContent, f.translate('quiqqer/order', locales.next));
});

test('imports PHP options without overwriting explicit JavaScript options', () => {
    const texts = {next: 'PHP next', steps: {Basket: {previous: 'PHP previous'}}};
    const f = fixture({imported: JSON.stringify(texts)});
    f.instance.$readButtonTexts();
    f.instance.$applyButtonTexts();
    assert.equal(f.button('next').textContent, 'PHP next');
    assert.equal(f.button('previous').textContent, 'PHP previous');
    f.attributes.buttonTexts = {next: 'JS next'};
    f.instance.$readButtonTexts();
    f.instance.$applyButtonTexts();
    assert.equal(f.button('next').textContent, 'JS next');
});

for (const imported of ['broken JSON', 'null', '[]', 'false', '123', '"next"']) {
    test(`malformed imported PHP options keep the defaults (${imported})`, () => {
        const f = fixture({imported});
        f.instance.$readButtonTexts();
        f.instance.$applyButtonTexts();
        assert.equal(f.button('next').textContent, f.translate('quiqqer/order', locales.next));
    });
}

test('AJAX rendering reapplies step labels to the newly created buttons', async () => {
    const f = fixture({texts: {next: 'Continue', steps: {CustomerData: {next: 'Review your order'}}}});
    f.instance.$refreshButtonEvents();
    const previous = f.button('next');
    assert.equal(previous.textContent, 'Continue');
    await f.instance.$renderResult({step: 'CustomerData', html: {buttons: ['next', 'previous']}}, 0);
    assert.notEqual(f.button('next'), previous);
    assert.equal(f.button('next').textContent, 'Review your order');
    assert.equal(f.attributes.current, 'CustomerData');
    await f.instance.$renderResult({step: 'Basket', html: {buttons: ['next', 'backToShop']}}, 0);
    assert.equal(f.button('next').textContent, 'Continue');
});

test('HTML-looking labels are assigned as text and no button action is changed', () => {
    const attack = '<img src=x onerror=alert(1)> & "Continue"';
    const f = fixture({texts: {next: attack}});
    const button = f.button('next');
    button.name = 'step';
    button.value = 'CustomerData';
    Object.defineProperty(button, 'innerHTML', {set() { throw Error('HTML must never be interpreted'); }});
    f.instance.$applyButtonTexts();
    assert.equal(button.textContent, attack);
    assert.equal(button.name, 'step');
    assert.equal(button.value, 'CustomerData');
});
