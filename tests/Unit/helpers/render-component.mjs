import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { compileFunction } from 'node:vm';
import { act } from 'react';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const multiplexRequire = createRequire(require.resolve('@laravel/multiplex'));
const inkRequire = createRequire(multiplexRequire.resolve('ink'));
const reconciler = inkRequire('react-reconciler');
const { ConcurrentRoot, DefaultEventPriority } = inkRequire(
    'react-reconciler/constants',
);
const hostContext = {};
let updatePriority = DefaultEventPriority;

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

function appendChild(parent, child) {
    const previousIndex = parent.children.indexOf(child);
    if (previousIndex !== -1) parent.children.splice(previousIndex, 1);
    parent.children.push(child);
}

function removeChild(parent, child) {
    parent.children.splice(parent.children.indexOf(child), 1);
}

function insertBefore(parent, child, before) {
    const previousIndex = parent.children.indexOf(child);
    if (previousIndex !== -1) parent.children.splice(previousIndex, 1);
    parent.children.splice(parent.children.indexOf(before), 0, child);
}

const renderer = reconciler({
    supportsMutation: true,
    isPrimaryRenderer: true,
    getRootHostContext: () => hostContext,
    getChildHostContext: () => hostContext,
    getPublicInstance: (instance) => instance,
    prepareForCommit: () => null,
    resetAfterCommit() {},
    createInstance: (type, props) => ({ type, props, children: [] }),
    createTextInstance: (text) => ({ text, children: [] }),
    appendInitialChild: appendChild,
    appendChild,
    appendChildToContainer: appendChild,
    removeChild,
    removeChildFromContainer: removeChild,
    insertBefore,
    insertInContainerBefore: insertBefore,
    finalizeInitialChildren: () => false,
    shouldSetTextContent: () => false,
    commitUpdate(instance, type, previousProps, nextProps) {
        instance.props = nextProps;
    },
    commitTextUpdate(instance, previousText, nextText) {
        instance.text = nextText;
    },
    clearContainer(container) {
        container.children = [];
    },
    detachDeletedInstance() {},
    setCurrentUpdatePriority(priority) {
        updatePriority = priority;
    },
    getCurrentUpdatePriority: () => updatePriority,
    resolveUpdatePriority: () => updatePriority || DefaultEventPriority,
    trackSchedulerEvent() {},
    resolveEventType: () => null,
    resolveEventTimeStamp: () => -1,
    shouldAttemptEagerTransition: () => false,
    maySuspendCommit: () => false,
    maySuspendCommitOnUpdate: () => false,
    maySuspendCommitInSyncRender: () => false,
    scheduleTimeout: setTimeout,
    cancelTimeout: clearTimeout,
    noTimeout: -1,
});

export function loadComponent(sourceUrl, replacements = {}) {
    const source = readFileSync(sourceUrl, 'utf8');
    const { outputText } = ts.transpileModule(source, {
        fileName: fileURLToPath(sourceUrl),
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            jsx: ts.JsxEmit.ReactJSX,
            esModuleInterop: true,
            target: ts.ScriptTarget.ESNext,
        },
    });
    const module = { exports: {} };
    const localRequire = createRequire(sourceUrl);
    const execute = compileFunction(
        outputText,
        ['require', 'module', 'exports'],
        {
            filename: fileURLToPath(sourceUrl),
        },
    );
    execute(
        (specifier) => replacements[specifier] ?? localRequire(specifier),
        module,
        module.exports,
    );
    return module.exports;
}

export async function renderComponent(element) {
    const container = { children: [] };
    const reportError = (error) => {
        throw error;
    };
    const root = renderer.createContainer(
        container,
        ConcurrentRoot,
        null,
        false,
        null,
        '',
        reportError,
        reportError,
        reportError,
    );
    const render = async (nextElement) => {
        await act(async () => renderer.updateContainer(nextElement, root));
    };
    const findAll = (predicate) => {
        const matches = [];
        const visit = (node) => {
            if (node.type && predicate(node)) matches.push(node);
            node.children.forEach(visit);
        };
        visit(container);
        return matches;
    };
    await render(element);
    return {
        findAll,
        find(predicate) {
            const matches = findAll(predicate);
            assert.equal(matches.length, 1, 'Expected one matching element');
            return matches[0];
        },
        render,
        unmount: () => render(null),
    };
}

export { act };
