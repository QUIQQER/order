// Run with: node --test tests/javascript/basket-conditions.test.cjs
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

const source = readFileSync(path.join(__dirname, '../../bin/frontend/classes/Basket.js'), 'utf8');
const storageKey = 'quiqqer-basket-products';

function createBasket({userId = 0, storage = new Map(), catalog = {}, merge = 1} = {}) {
    const messages = [];
    const errors = [];
    const separateOrders = [];
    let saved = [];
    let definition;
    let redirect;
    const redirected = new Promise(resolve => { redirect = resolve; });
    const window = {QUIQQER_ORDER_ORDER_PROCESS_MERGE: merge};
    Object.defineProperty(window, 'location', {set: redirect});
    class Product {
        constructor({id, quantity = 1, fields = {}}) {
            this.$data = {id, fields: [{type: 'BasketConditions', value: catalog[id]?.condition ?? 1}]};
            this.calc = {type: catalog[id]?.type ?? 'Product'};
            this.options = {id};
            this.$quantity = quantity;
            this.fields = fields;
        }
        async refresh() {
            await catalog[this.$data.id]?.wait;
            return this;
        }
        async setQuantity(quantity) { this.$quantity = quantity; }
        async setFieldValues(fields) {
            if (catalog[this.$data.id]?.invalid) {
                throw new Error('Invalid product options');
            }
            this.fields = fields;
        }
        getAttributes() {
            // The real product serializer overwrites the catalog fields with custom fields.
            this.$data.fields = this.fields;
            return {id: this.$data.id, quantity: this.$quantity, fields: this.fields};
        }
    }
    const QUI = {
        Storage: {
            get: key => storage.get(key),
            set: (key, value) => storage.set(key, value),
            remove: key => storage.delete(key)
        },
        fireEvent() {},
        getMessageHandler: async () => ({addAttention: key => messages.push(key)})
    };
    const Ajax = {
        post(name, resolve, params) {
            saved = JSON.parse(params.products);
            resolve({products: saved});
        }
    };
    const context = vm.createContext({
        window, QUIQQER_USER: {id: userId}, console: {error: error => errors.push(error)},
        setTimeout, clearTimeout,
        typeOf: value => typeof value,
        Class: function (value) { return value; },
        require: (names, callback) => callback(Product),
        define: (name, names, factory) => {
            definition = factory(QUI, {}, async () => '/checkout', Ajax, {get: (group, key) => key});
        }
    });
    vm.runInContext(`
        JSON.encode = JSON.stringify;
        JSON.decode = JSON.parse;
        String.uniqueID = () => 'basket-id';
        Function.prototype.delay = function(ms, receiver) { return setTimeout(() => this.call(receiver), 0); };
    `, context);
    vm.runInContext(source, context);
    const attributes = {};
    const basket = Object.assign({}, definition, {
        parent() {},
        fireEvent() {},
        setAttribute: (name, value) => { attributes[name] = value; },
        getAttribute: name => attributes[name],
        existsProduct: async id => catalog[id]?.available !== false,
        addProductToOrderInProcess: async (id, fields, quantity) => {
            separateOrders.push({id, fields, quantity});
            return 'separate-hash';
        }
    });
    basket.initialize({});
    basket.$isLoaded = true;
    basket.$basketId = 'basket-id';
    return {basket, messages, errors, storage, separateOrders, redirected, saved: () => saved};
}

for (const userId of [0, 42]) {
    for (const condition of [2, 6, '2', '6']) {
        test(`empty basket retains standalone product (user ${userId}, condition ${condition})`, async () => {
            const fixture = createBasket({userId, catalog: {10: {condition}}});
            await fixture.basket.addProduct(10, 4, {engraving: 'Hello'});
            assert.deepEqual(fixture.saved(), [{
                id: 10, quantity: Number(condition) === 2 ? 1 : 4, fields: {engraving: 'Hello'}
            }]);
            assert.equal(fixture.separateOrders.length, 0);
            assert.equal(fixture.storage.has('condition-product'), false);
            assert.deepEqual(fixture.errors, []);
        });
    }

    for (const condition of [1, 2, 4, 6]) {
        test(`new product replaces standalone product (user ${userId}, new condition ${condition})`, async () => {
            const fixture = createBasket({userId, catalog: {10: {condition: 6}, 20: {condition}}});
            await fixture.basket.addProduct(10, 4);
            await fixture.basket.addProduct(20, 3);
            assert.deepEqual(fixture.saved().map(product => product.id), [20]);
            assert.deepEqual(fixture.messages, ['basket.standalone.product.replaced']);
            assert.equal(fixture.separateOrders.length, 0);
        });
    }

    test(`filled normal basket is preserved for separate checkout (user ${userId})`, async () => {
        const fixture = createBasket({userId, catalog: {20: {condition: 2}}});
        await fixture.basket.addProduct(10, 2);
        void fixture.basket.addProduct(20, 3, {engraving: 'Hello'});
        const url = await fixture.redirected;
        assert.deepEqual(fixture.saved(), [{id: 10, quantity: 2, fields: {}}]);
        assert.equal(url, userId ? '/checkout/separate-hash' : '/checkout');
        const pending = userId ? fixture.separateOrders[0] : JSON.parse(fixture.storage.get('condition-product'));
        assert.deepEqual(pending, {id: 20, fields: {engraving: 'Hello'}, quantity: 1});
    });
}

test('guest basket survives navigation and reload without starting a separate checkout', async () => {
    const catalog = {10: {condition: 6}};
    const initial = createBasket({catalog});
    await initial.basket.addProduct(10, 4, {engraving: 'Hello'});
    const reloaded = createBasket({catalog, storage: initial.storage});
    reloaded.basket.$isLoaded = false;
    await reloaded.basket.load();
    assert.deepEqual(reloaded.saved(), initial.saved());
    assert.equal(reloaded.separateOrders.length, 0);
});

test('queued additions respect click order even when the first refresh is slower', async () => {
    let refresh;
    const wait = new Promise(resolve => { refresh = resolve; });
    const fixture = createBasket({catalog: {10: {condition: 6, wait}}});
    const first = fixture.basket.addProduct(10, 4);
    const second = fixture.basket.addProduct(20, 2);
    refresh();
    await Promise.all([first, second]);
    assert.deepEqual(fixture.saved(), [{id: 20, quantity: 2, fields: {}}]);
});

test('failed validation of the incoming product preserves the standalone product', async () => {
    const fixture = createBasket({catalog: {10: {condition: 6}, 20: {invalid: true}}});
    await fixture.basket.addProduct(10, 4);
    await fixture.basket.addProduct(20, 2);
    assert.deepEqual(fixture.saved(), [{id: 10, quantity: 4, fields: {}}]);
    assert.equal(fixture.messages.length, 0);
    assert.equal(fixture.errors.length, 1);
});

test('quantity changes cannot exceed the single-unit condition', async () => {
    const fixture = createBasket({catalog: {10: {condition: 2}}});
    await fixture.basket.addProduct(10, 1);
    await fixture.basket.setQuantity(1, 7);
    assert.equal(fixture.saved()[0].quantity, 1);
});

test('guest options survive the transition to a separate checkout after login', async () => {
    const storage = new Map([['condition-product', JSON.stringify({id: 20, quantity: 3, fields: {engraving: 'Hi'}})]]);
    const fixture = createBasket({userId: 42, storage});
    void fixture.basket.$checkLocalBasketLoading();
    assert.equal(await fixture.redirected, '/checkout/separate-hash');
    assert.deepEqual(JSON.parse(JSON.stringify(fixture.separateOrders)), [
        {id: 20, quantity: 3, fields: {engraving: 'Hi'}}
    ]);
    assert.equal(storage.has('condition-product'), false);
});

test('an account basket retains the condition after loading and saving before adding a product', async () => {
    const fixture = createBasket({userId: 42, catalog: {10: {condition: 6}}});
    await fixture.basket.$loadData({id: 'basket-id', products: [{id: 10, quantity: 4, fields: {}}]});
    await fixture.basket.save();
    await fixture.basket.addProduct(20, 2);
    assert.deepEqual(fixture.saved(), [{id: 20, quantity: 2, fields: {}}]);
    assert.deepEqual(fixture.messages, ['basket.standalone.product.replaced']);
});
