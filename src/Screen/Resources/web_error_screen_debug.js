var Kuria;
(function (Kuria) {
    (function (Error) {
        function blurSelectedTextarea()
        {
            delete this.dataset.selected;
        }

        Error.WebErrorScreen = {
            toggle: function (elementId, control) {
                var element = document.getElementById(elementId);

                if (element) {
                    if (element.style.display === '' || element.style.display === 'none') {
                        element.style.display = 'block';
                        control.className = 'toggle-control open';
                    } else {
                        element.style.display = 'none';
                        control.className = 'toggle-control closed';
                    }
                }
            },

            toggleTrace: function (traceId) {
                var trace = document.getElementById('trace-' + traceId);
                var traceExtra = document.getElementById('trace-extra-' + traceId);

                if (trace && traceExtra) {
                    if (traceExtra.style.display === '' || traceExtra.style.display === 'none') {
                        // show
                        trace.className = 'trace expandable open';
                        try {
                            traceExtra.style.display = 'table-row';
                        } catch (e) {
                            // IE7
                            traceExtra.style.display = 'block';
                        }
                    } else {
                        // hide
                        trace.className = 'trace expandable closed';
                        traceExtra.style.display = 'none';
                    }
                }
            },

            showTextareaAsHtml: function (textareaId, link) {
                var textarea = document.getElementById(textareaId),
                    iframe = document.getElementById('html-preview-' + textareaId);

                if (textarea) {
                    if (iframe) {
                        iframe.parentNode.removeChild(iframe);
                        link.textContent = 'Show as HTML';
                    } else {
                        iframe = document.createElement('iframe');
                        iframe.src = 'about:blank';
                        iframe.id = 'html-preview-' + textareaId;

                        iframe = textarea.parentNode.insertBefore(iframe, textarea.nextSibling);

                        iframe.contentWindow.document.open('text/html');
                        iframe.contentWindow.document.write(textarea.value);
                        iframe.contentWindow.document.close();

                        link.textContent = 'Hide';
                    }
                }
            },

            selectTextareaContent: function (textarea) {
                if (textarea.dataset) {
                    if (!textarea.dataset.selectInitialized) {
                        textarea.addEventListener('blur', blurSelectedTextarea);
                        textarea.dataset.selectInitialized = 1;
                    }

                    if (!textarea.dataset.selected) {
                        textarea.select();
                        textarea.dataset.selected = 1;
                    }
                } else {
                    // old browsers
                    textarea.select();
                }
            }
        };

    })(Kuria.Error || (Kuria.Error = {}));
})(Kuria || (Kuria = {}));
