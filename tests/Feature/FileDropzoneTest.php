<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class FileDropzoneTest extends TestCase
{
    public function test_file_selection_drag_states_and_validation(): void
    {
        $script = <<<'JS'
const assert = require('node:assert/strict');
const fs = require('node:fs');
const ts = require('typescript');
const source = fs.readFileSync('resources/js/composables/useFileDropzone.ts', 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
const moduleExports = {};
new Function('require', 'exports', compiled)(require, moduleExports);
const { ref } = require('vue');
const disabled = ref(false);
let selected = null;
let error = '';
const zone = moduleExports.useFileDropzone({
    disabled: () => disabled.value,
    extensions: () => ['png', 'pdf'],
    maxSizeKb: () => 10,
    onSelect: (file) => { selected = file; error = ''; },
    onError: (message) => { error = message; },
});
let prevented = 0;
function event(files = [], types = ['Files']) {
    return { preventDefault() { prevented++; }, dataTransfer: { files, types, dropEffect: '' } };
}
const image = new File(['image'], 'IMAGE.PNG', { type: 'image/png' });
zone.dragEnter(event());
zone.dragEnter(event());
zone.dragLeave(event());
assert.equal(zone.isDragging.value, true, 'Moving across children must keep the highlight');
zone.drop(event([image]));
assert.equal(zone.isDragging.value, false);
assert.equal(selected, image, 'Dropped file must become the selected File object');
assert.equal(error, '');
const pdf = new File(['pdf'], 'document.pdf');
zone.selectFiles([pdf]);
assert.equal(selected, pdf, 'Picker selection must use the same pipeline');
zone.selectFiles([]);
assert.equal(selected, pdf, 'Cancelling the picker must preserve selection');
zone.drop(event([image, pdf]));
assert.match(error, /seul fichier/);
assert.equal(selected, pdf, 'Multiple files must not silently replace the selected file');
zone.selectFiles([new File(['x'], 'script.svg')]);
assert.match(error, /format/);
zone.selectFiles([new File([new Uint8Array(10241)], 'large.pdf')]);
assert.match(error, /taille/);
const exact = new File([new Uint8Array(10240)], 'exact.pdf');
zone.selectFiles([exact]);
assert.equal(selected, exact);
assert.equal(error, '');
zone.dragEnter(event([], ['text/plain']));
assert.equal(zone.isDragging.value, false, 'Dragging text must not highlight the area');
zone.dragLeave(event());
zone.dragLeave(event());
zone.dragEnter(event());
assert.equal(zone.isDragging.value, true, 'Unbalanced leave events must not cause a negative counter');
disabled.value = true;
assert.equal(zone.isDragging.value, false);
const blocked = event([image]);
zone.dragOver(blocked);
assert.equal(blocked.dataTransfer.dropEffect, 'none');
zone.drop(blocked);
zone.selectFiles([image]);
assert.equal(selected, exact, 'An in-progress upload must not change files');
disabled.value = false;
assert.equal(zone.isDragging.value, false);
const allowed = event();
zone.dragOver(allowed);
assert.equal(allowed.dataTransfer.dropEffect, 'copy');
assert.ok(prevented > 0, 'Browser navigation must be prevented for drops');
console.log('Dropzone behavior passed');
JS;
        $process = new Process(['node', '-e', $script], base_path());
        $process->setTimeout(30);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
        $this->assertStringContainsString('Dropzone behavior passed', $process->getOutput());
    }
}
