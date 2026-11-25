(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    ready(function () {
        var wrap = document.querySelector('.wpdsai-wrap');
        var question = document.getElementById('wpdsai-question');
        var send = document.getElementById('wpdsai-send');
        var answer = document.querySelector('#wpdsai-answer .wpdsai-answer-text');
        var counter = document.getElementById('wpdsai-counter');
        var config = window.wpDeepSeekAI || (wrap ? {
            endpoint: wrap.getAttribute('data-endpoint'),
            nonce: wrap.getAttribute('data-nonce')
        } : null);

        if (!question || !send || !answer || !counter || !config || !config.endpoint || !config.nonce) {
            return;
        }

        function updateCounter() {
            counter.textContent = question.value.length + ' / 2000';
        }

        function setBusy(busy) {
            send.disabled = busy;
            question.disabled = busy;
            send.textContent = busy ? 'AI 正在思考…' : '发送问题';
        }

        function showMessage(message, isError) {
            answer.textContent = message;
            answer.parentElement.classList.toggle('is-error', Boolean(isError));
        }

        function submitQuestion() {
            var value = question.value.trim();

            if (!value) {
                showMessage('请先输入问题。', true);
                question.focus();
                return;
            }

            setBusy(true);
            showMessage('正在连接 DeepSeek，请稍候……', false);

            fetch(config.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify({ question: value })
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            throw new Error(data.message || '请求失败，请稍后重试。');
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    showMessage(data.answer || 'AI 未返回内容。', false);
                })
                .catch(function (error) {
                    showMessage(error.message || '连接失败，请稍后重试。', true);
                })
                .then(function () {
                    setBusy(false);
                });
        }

        question.addEventListener('input', updateCounter);
        question.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                submitQuestion();
            }
        });
        send.addEventListener('click', submitQuestion);
        updateCounter();
    });
}());

