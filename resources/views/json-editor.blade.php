@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Translations Editor</span>
                    <div>
                        <div class="btn-group" role="group" aria-label="Language toggle">
                            <a href="?lang=en" class="btn btn-sm btn-outline-primary{{ $lang === 'en' ? ' active' : '' }}">EN</a>
                            <a href="?lang=ar" class="btn btn-sm btn-outline-primary{{ $lang === 'ar' ? ' active' : '' }}">AR</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <style>
                        .card-body {
                            max-width: 800px;
                            margin: 0 auto;
                            padding: 32px 32px 24px 32px;
                        }
                        .btn-group .btn {
                            min-width: 48px;
                            border-color: rgb(60,75,100) !important;
                            color: rgb(60,75,100);
                        }
                        .btn-group .btn.active, .btn-group .btn:active {
                            background: rgb(60,75,100) !important;
                            color: #fff !important;
                            border-color: rgb(60,75,100) !important;
                        }
                        .btn-group .btn:hover, .btn-group .btn:focus {
                            background: rgb(90,110,140) !important;
                            color: #fff !important;
                            border-color: rgb(90,110,140) !important;
                        }
                        .CodeMirror { height: 300px; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 24px; }
                        .field-row {
                            margin-bottom: 18px;
                            display: flex;
                            align-items: center;
                            padding: 8px 0;
                        }
                        .field-label {
                            min-width: 180px;
                            display: inline-block;
                            font-size: 1rem;
                            color: #333;
                        }
                        .group-label {
                            font-weight: bold;
                            font-size: 1.08rem;
                            margin: 18px 0 8px 0;
                            color: rgb(60,75,100);
                            padding-left: 2px;
                        }
                        input[type="text"] {
                            flex: 1;
                            padding: 7px 10px;
                            border: 1px solid #d0d0d0;
                            border-radius: 5px;
                            font-size: 1rem;
                            background: #f8fafc;
                            transition: border 0.2s;
                        }
                        input[type="text"]:focus {
                            border: 1.5px solid #007bff;
                            outline: none;
                            background: #fff;
                        }
                        button[type="submit"] {
                            background: rgb(60,75,100);
                            color: #fff;
                            border: none;
                            border-radius: 5px;
                            padding: 10px 24px;
                            font-size: 1rem;
                            cursor: pointer;
                            margin-top: 18px;
                            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
                            transition: background 0.2s;
                        }
                        button[type="submit"]:hover {
                            background: rgb(90,110,140);
                        }
                    </style>
                    @if(session('success'))
                        <p style="color: green">{{ session('success') }}</p>
                    @endif
                    <form method="POST" action="{{ route('json-editor.save') }}" id="json-form">
                        @csrf
                        <input type="hidden" name="lang" value="{{ $lang }}">
                        <div id="form-container"></div>
                        <textarea id="json-editor" name="json" style="display:none;">{{ json_encode($jsonData, JSON_PRETTY_PRINT) }}</textarea>
                        <button type="submit">Save</button>
                    </form>
                    <div style="margin-top: 32px;">
                        <label style="font-weight: bold; color: #555; margin-bottom: 8px; display: block;">Raw JSON Editor</label>
                        <div id="editor-wrapper"></div>
                    </div>
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.9/codemirror.min.css">
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.9/codemirror.min.js"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.9/mode/javascript/javascript.min.js"></script>
                    <script>
                        // Move CodeMirror to a custom wrapper below the form
                        const textarea = document.getElementById('json-editor');
                        const editor = CodeMirror(function(elt) {
                            document.getElementById('editor-wrapper').appendChild(elt);
                        }, {
                            value: textarea.value,
                            mode: { name: "application/json", json: true },
                            lineNumbers: true,
                            tabSize: 2,
                        });
                        // Hide the textarea
                        textarea.style.display = 'none';
                        // Helper: set value by dot path
                        function setValueByPath(obj, path, value) {
                            const keys = path.split('.');
                            let o = obj;
                            for (let i = 0; i < keys.length - 1; i++) {
                                if (!(keys[i] in o)) o[keys[i]] = {};
                                o = o[keys[i]];
                            }
                            o[keys[keys.length - 1]] = value;
                        }
                        // Helper: get value by dot path
                        function getValueByPath(obj, path) {
                            return path.split('.').reduce((o, k) => (o || {})[k], obj);
                        }
                        // Recursively generate form fields for all primitive values
                        function generateFormFields(jsonObj, parentKey = '', parentIsGroup = false) {
                            const container = document.getElementById('form-container');
                            if (parentKey === '') container.innerHTML = '';
                            Object.keys(jsonObj).forEach(key => {
                                const value = jsonObj[key];
                                const fullKey = parentKey ? parentKey + '.' + key : key;
                                if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                                    // Object: recurse
                                    const group = document.createElement('div');
                                    group.style.marginLeft = parentKey ? '24px' : '0';
                                    const groupLabel = document.createElement('div');
                                    groupLabel.className = 'group-label';
                                    groupLabel.innerText = key;
                                    group.appendChild(groupLabel);
                                    container.appendChild(group);
                                    generateFormFields(value, fullKey, true);
                                } else if (Array.isArray(value)) {
                                    // Array: show index for each primitive
                                    value.forEach((item, idx) => {
                                        if (typeof item === 'object' && item !== null) {
                                            const group = document.createElement('div');
                                            group.style.marginLeft = '24px';
                                            const groupLabel = document.createElement('div');
                                            groupLabel.className = 'group-label';
                                            groupLabel.innerText = `${key}[${idx}]`;
                                            group.appendChild(groupLabel);
                                            container.appendChild(group);
                                            generateFormFields(item, `${fullKey}[${idx}]`, true);
                                        } else {
                                            const row = document.createElement('div');
                                            row.className = 'field-row';
                                            const label = document.createElement('label');
                                            label.className = 'field-label';
                                            // Only show index as label if inside group
                                            label.innerText = parentIsGroup ? `[${idx}]` : `${key}[${idx}]`;
                                            const input = document.createElement('input');
                                            input.type = 'text';
                                            input.value = item;
                                            input.style.marginLeft = '10px';
                                            input.addEventListener('blur', () => {
                                                let updated = JSON.parse(editor.getValue());
                                                // Set value in array
                                                let arr = getValueByPath(updated, parentKey);
                                                arr[idx] = input.value;
                                                editor.setValue(JSON.stringify(updated, null, 2));
                                            });
                                            row.appendChild(label);
                                            row.appendChild(input);
                                            container.appendChild(row);
                                        }
                                    });
                                } else {
                                    // Primitive value
                                    const row = document.createElement('div');
                                    row.className = 'field-row';
                                    const label = document.createElement('label');
                                    label.className = 'field-label';
                                    // Show only last segment if inside group
                                    let labelText = key;
                                    if (parentIsGroup) {
                                        labelText = key;
                                    } else if (parentKey) {
                                        labelText = fullKey;
                                    }
                                    label.innerText = labelText;
                                    const input = document.createElement('input');
                                    input.type = 'text';
                                    input.value = value;
                                    input.style.marginLeft = '10px';
                                    input.addEventListener('blur', () => {
                                        let updated = JSON.parse(editor.getValue());
                                        setValueByPath(updated, fullKey, input.value);
                                        editor.setValue(JSON.stringify(updated, null, 2));
                                    });
                                    row.appendChild(label);
                                    row.appendChild(input);
                                    container.appendChild(row);
                                }
                            });
                        }
                        // Initial render
                        try {
                            const initialJson = JSON.parse(editor.getValue());
                            generateFormFields(initialJson);
                        } catch (e) {
                            console.warn("Invalid JSON can't generate fields");
                        }
                        // Sync editor changes to fields (optional: watch changes in editor)
                        editor.on('change', () => {
                            try {
                                const updatedJson = JSON.parse(editor.getValue());
                                generateFormFields(updatedJson);
                            } catch (e) {
                                // ignore if JSON invalid
                            }
                        });
                        // On form submit, update textarea with latest editor value
                        document.getElementById('json-form').addEventListener('submit', function(e) {
                            document.getElementById('json-editor').value = editor.getValue();
                        });
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
