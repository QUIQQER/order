// Run with: node --test tests/javascript/lock-message.test.cjs
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');
const Mustache = require('../../../../bin/quiqqer-asset/mustache/mustache/mustache.js');

const root = path.resolve(__dirname, '../..');
const packageName = path.basename(root);
const panelName = packageName === 'contracts' ? 'Contract' : 'Order';
const source = readFileSync(path.join(root, `bin/backend/controls/panels/${panelName}.js`), 'utf8');
const owner = 'e7b4bb5c-539f-4dd8-a0d1-716684a12e72';

function createPanel({language = 'de', isSU = false, attributes = {username: 'Mor', id: 42}} = {}) {
    const locale = readFileSync(path.join(root, `locale/${language}.xml`), 'utf8');
    const dialogs = [];
    const errors = [];
    const requestedUsers = [];
    let resolveUser;
    let rejectUser;
    let definition;
    const loaded = new Promise((resolve, reject) => {
        resolveUser = () => resolve({getAttributes: () => attributes});
        rejectUser = reject;
    });
    const dependencies = {
        Mustache,
        Users: {
            get(id) {
                requestedUsers.push(id);
                return {loadIfNotLoaded: () => loaded};
            }
        },
        Locale: {
            get(group, key, values = {}) {
                const block = locale.split(`<locale name="${key}"`)[1]?.split('</locale>')[0];
                const text = block?.match(/<!\[CDATA\[([\s\S]*?)\]\]>/)?.[1] ?? key;
                return text.replace(/\[(\w+)\]/g, (placeholder, name) => values[name] ?? placeholder);
            }
        },
        'qui/controls/windows/Confirm': function (options) {
            this.open = () => dialogs.push(options);
        }
    };
    vm.runInNewContext(source, {
        window: {USER: {isSU}},
        console: {error: error => errors.push(error)},
        Class: function (options) { return options; },
        define(name, names, factory) {
            definition = factory(...names.map(name => dependencies[name] ?? {}));
        }
    });
    const panel = {
        $locked: owner,
        Loader: {
            visible: false,
            show() { this.visible = true; },
            hide() { this.visible = false; }
        },
        unlocks: 0,
        unlockPanel() {
            this.unlocks++;
            return Promise.resolve();
        }
    };
    return {
        panel, dialogs, errors, requestedUsers, resolveUser, rejectUser,
        show: () => definition.$showLockMessage.call(panel)
    };
}

for (const language of ['de', 'en', 'es', 'fr', 'it', 'pl', 'pt']) {
    test(`resolves the lock owner's UUID before displaying the ${language} message`, async () => {
        const fixture = createPanel({language});
        const pending = fixture.show();
        assert.deepEqual(fixture.requestedUsers, [owner]);
        assert.equal(fixture.dialogs.length, 0);
        assert.equal(fixture.panel.Loader.visible, true);
        fixture.resolveUser();
        await pending;
        assert.equal(fixture.dialogs.length, 1);
        assert.match(fixture.dialogs[0].information, /Mor \(42\)/);
        assert.doesNotMatch(fixture.dialogs[0].information, /\[(username|id)\]/);
        assert.equal(fixture.panel.$locked, owner);
        assert.equal(fixture.panel.Loader.visible, false);
        assert.deepEqual(fixture.errors, []);
    });
}

test('escapes user attributes in the HTML dialog', async () => {
    const fixture = createPanel({attributes: {username: '<img src=x onerror=alert(1)>', id: '42<>'}});
    const pending = fixture.show();
    fixture.resolveUser();
    await pending;
    assert.match(fixture.dialogs[0].information, /&lt;img/);
    assert.match(fixture.dialogs[0].information, /42&lt;&gt;/);
    assert.doesNotMatch(fixture.dialogs[0].information, /<img/);
});

for (const isSU of [false, true]) {
    test(`preserves unlock permission for isSU=${isSU}`, async () => {
        const fixture = createPanel({isSU});
        const pending = fixture.show();
        fixture.resolveUser();
        await pending;
        let closed = false;
        fixture.dialogs[0].events.onSubmit({
            close() { closed = true; },
            Loader: {show() {}}
        });
        await Promise.resolve();
        assert.equal(fixture.panel.unlocks, isSU ? 1 : 0);
        assert.equal(closed, true);
    });
}

test('clears the loader and reports a failed user lookup', async () => {
    const fixture = createPanel();
    const pending = fixture.show();
    const error = new Error('User lookup failed');
    fixture.rejectUser(error);
    await pending;
    assert.equal(fixture.panel.Loader.visible, false);
    assert.equal(fixture.dialogs.length, 0);
    assert.deepEqual(fixture.errors, [error]);
});
