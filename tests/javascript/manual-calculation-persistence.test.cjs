const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');
const copy = value => JSON.parse(JSON.stringify(value));
const snapshot = () => JSON.parse(readFileSync(join(__dirname, '../Fixtures/ManualArticleList.json'), 'utf8'));

function definition(dependencies = {}, globals = {}) {
    let control;
    vm.runInNewContext(readFileSync(join(__dirname, '../../bin/backend/controls/panels/Order.js'), 'utf8'), {
        console,
        Class: function(value) { return value; },
        define(id, names, factory) { control = factory(...names.map(name => dependencies[name] || {})); },
        ...globals
    });
    return control;
}

function panel(control, state) {
    const attributes = {orderId: 'fixture', articles: []};
    return {
        ...control,
        $serializedList: state,
        Loader: {show() {}, hide() {}},
        getAttribute: name => attributes[name],
        setAttribute(name, value) { attributes[name] = value; },
        getContent: () => ({getElement: () => null}),
        $dialogStatusChangeNotification: () => Promise.resolve(''),
        $dialogShippingStatusChangeNotification: () => Promise.resolve(''),
        getActiveCategory: () => null,
        refresh: () => Promise.resolve(),
        showSavedIconAnimation() {}
    };
}

test('order update sends all manual totals, taxes and price factors after unloading positions', async () => {
    const original = snapshot();
    original.priceFactors = [{title: 'Rundung', sum: 0.0001}];
    let sent;
    const control = definition({
        'package/quiqqer/order/bin/backend/Orders': {
            updateOrder(id, data) { sent = copy(data); return Promise.resolve({}); }
        }
    });
    const editor = panel(control, {});
    editor.$ArticleList = {
        getCalculationMode: () => 'manual',
        serialize: () => copy(original),
        save() { throw new Error('Positions alone would discard the manual totals.'); }
    };
    await editor.update();
    assert.deepEqual(sent.articles, original);
    assert.deepEqual(sent.priceFactors, original.priceFactors);
});

test('saving another category preserves the loaded manual snapshot even with no positions', async () => {
    const original = snapshot();
    original.articles = [];
    let sent;
    const editor = panel(definition({
        'package/quiqqer/order/bin/backend/Orders': {
            updateOrder(id, data) { sent = copy(data); return Promise.resolve({}); }
        }
    }), original);
    await editor.update();
    assert.deepEqual(sent.articles, original);
});

test('automatic saves keep the historical positions array and separate factors', async () => {
    const original = snapshot();
    original.calculationMode = 'automatic';
    let sent;
    const editor = panel(definition({
        'package/quiqqer/order/bin/backend/Orders': {
            updateOrder(id, data) { sent = copy(data); return Promise.resolve({}); }
        }
    }), original);
    let saved = 0;
    editor.$ArticleList = {
        getCalculationMode: () => 'automatic',
        save() { saved++; return copy(original.articles); },
        serialize: () => copy(original)
    };
    await editor.update();
    assert.equal(saved, 1);
    assert.deepEqual(sent.articles, original.articles);
    assert.deepEqual(sent.priceFactors, original.priceFactors);
});

test('opening positions waits for asynchronous manual restoration before hiding the loader', async () => {
    const original = snapshot();
    let finish;
    let opened = 0;
    let hidden = 0;
    const restored = new Promise(resolve => { finish = resolve; });
    const Container = {style: {}, setStyle() {}, getParent() { return this; }};
    class List {
        inject() { return this; }
        unserialize(data) { assert.deepEqual(copy(data), original); return restored; }
    }
    class Summary { inject() { return this; } getElm() { return {}; } }
    const editor = panel(definition({}, {
        require(names, callback) { callback(List, Summary); },
        moofx: () => ({animate() {}})
    }), original);
    editor.$closeCategory = () => Promise.resolve(Container);
    editor.$openCategory = () => { opened++; return Promise.resolve(); };
    editor.getCategory = () => ({setActive() {}});
    editor.Loader.hide = () => { hidden++; };
    for (const key of ['$AddProduct', '$AddSeparator', '$SortSeparator', '$ArticleSort']) {
        editor[key] = {show() {}};
    }
    let done = false;
    const opening = editor.openArticles().then(() => { done = true; });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(done, false);
    assert.equal(opened, 0);
    assert.equal(hidden, 0);
    finish();
    await opening;
    assert.equal(opened, 1);
    assert.equal(hidden, 1);
});
