// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const icinga = { availableModules: {} };
const source = fs.readFileSync(path.join(__dirname, '../../public/js/module.js'), 'utf8');
vm.runInNewContext(source, { Icinga: icinga });

function selectedValue(text, context)
{
    const director = Object.create(icinga.availableModules.director.prototype);
    director.getSuggestionList = () => ({ remove() {} });

    let value;
    const input = {
        data(key) {
            return key === 'suggestion-context' ? context : undefined;
        },
        focus() { return this; },
        val(next) {
            if (arguments.length === 0) {
                return value;
            }
            value = next;
            return this;
        },
        trigger() { return this; }
    };
    const suggestion = {
        text: () => text,
        closest: () => ({ siblings: () => input })
    };
    director.chooseSuggestion(suggestion);
    return value;
}

test('service autocomplete preserves a literal bracketed suffix', () => {
    assert.equal(selectedValue('whatever [A]', 'servicenames'), 'whatever [A]');
    assert.equal(selectedValue('check disk [123]', 'servicenames'), 'check disk [123]');
});

test('other suggestion contexts retain the existing label/key handling', () => {
    assert.equal(selectedValue('Display label [internalKey]', 'dataListValues'), 'internalKey');
    assert.equal(selectedValue('ordinary-service', 'servicenames'), 'ordinary-service');
});
