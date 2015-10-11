(function (window) {
    var serviceUrl = window.document.getElementsByTagName("body")[0].getAttribute('service-url');
    serviceUrl = 'http://localhost:8000';

    window.AOS = {}

    window.AOS.CreateMessage = function (message, success) {
        success = Boolean(success);

        var messageElement = null;

        if (message) {
            messageElement = window.document.createElement('div');
            window.AOS.AddClass(messageElement, 'message');
            window.AOS.AddClass(messageElement, (success ? 'success' : 'warning'));
            messageElement.innerHTML = '<p>' + message + '</p>';

            setTimeout(function () {
                window.AOS.RemoveElement(messageElement);
            }, 10000);
        }

        return messageElement;
    }

    window.AOS.DisplayMessage = function (message, success) {
        success = success || true;
        var messageElement = window.AOS.CreateMessage(message, success);
        if (messageElement) {
            var container = window.document.getElementById('messages');
            if (container) {
                container.appendChild(messageElement);
            }
        }
    };

    window.AOS.CreateForm = function (form) {
        var errorClass = 'error';
        form.clearErrors = function () {
            var elements = form.elements;
            var labels = form.querySelectorAll('small.'+errorClass);

            for (var j = 0; j < elements.length; j++) {
                var input = elements[j];
                if (window.AOS.HasClass(input, errorClass)) {
                    window.AOS.RemoveClass(input, errorClass);
                }
            }

            for (var j = 0; j < labels.length; j++) {
                window.AOS.RemoveElement(labels[j]);
            }

            return form;
        }

        form.displayErrors = function (errors) {
            var children = form.elements;

            for (name in errors) {
                var message = errors[name].join(' ');
                for (var j = 0; j < children.length; j++) {
                    var input = children[j];
                    if (input.getAttribute && input.getAttribute('name') === name) {
                        var errorLabel = window.document.createElement('small');
                        window.AOS.AddClass(errorLabel, errorClass);
                        errorLabel.innerHTML = message;
                        input.parentNode.insertBefore(errorLabel, input.nextSibling);
                        window.AOS.AddClass(input, errorClass);
                    }
                }
            }
        };
        form.onsubmit = function (e) {
            var action = form.getAttribute('action');
            if (action !== null) {
                var data = window.AOS.SerializeForm(form.clearErrors());

                window.AOS.Http('POST', action, data).then(
                    function (response) {
                        if (response.message) {
                            window.AOS.DisplayMessage(response.message);
                        }
                    }, function (error) {
                        form.displayErrors(error.response);
                    }
                );

                if (e.preventDefault) {
                    e.preventDefault();
                } else {
                    e.returnValue = false;
                }
            }
        };
    };

    window.AOS.AddClass = function (element, className) {
        return element.className = element.className.concat(' ' + className);
    };

    window.AOS.HasClass = function (element, className) {
        return (' ' + element.className + ' ').indexOf(' ' + className + ' ') > -1;
    };

    window.AOS.RemoveClass = function (element, className) {
        return element.className = element.className.replace(new RegExp(className, 'g'), '');
    };

    window.AOS.RemoveElement = function (element) {
        return element.parentNode.removeChild(element);
    };

    window.AOS.Http = function (method, url, data) {
        var data = data || '';
        url = serviceUrl + '' + url;

        var result = {};

        if (data !== '') {
            var pairs = data.slice(0).split('&');
            pairs.forEach(function(pair) {
                pair = pair.split('=');
                result[pair[0]] = decodeURIComponent(pair[1] || '');
            });
        }

        return new Promise(function(resolve, reject) {
            var req = new XMLHttpRequest();
            req.open(method, url);
            req.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
            req.onload = function() {
                if (req.status == 200) {
                    resolve(JSON.parse(req.response));
                }
                else {
                    reject({
                        code: req.status,
                        reason: req.statusText,
                        response: JSON.parse(req.response)
                    });
                }
            };
            req.onerror = function() {
                reject(Error("Something went wrong ... "));
            };
            req.send(JSON.stringify(result));
        });
    }

    window.AOS.SerializeForm = function (form) {
        if (!form || form.nodeName != "FORM") {
            return;
        }

        var i, j, q = [];

        for (i = form.elements.length - 1; i >= 0; i = i - 1) {
            if (form.elements[i].name === "") {
                continue;
            }

            switch (form.elements[i].nodeName) {
                case 'INPUT':
                    switch (form.elements[i].type) {
                    case 'text':
                    case 'hidden':
                    case 'password':
                    case 'button':
                    case 'reset':
                    case 'submit':
                        q.push(form.elements[i].name + "=" + encodeURIComponent(form.elements[i].value));
                        break;
                    case 'checkbox':
                    case 'radio':
                        if (form.elements[i].checked) {
                                q.push(form.elements[i].name + "=" + encodeURIComponent(form.elements[i].value));
                        }
                        break;
                    }
                    break;
                    case 'file':
                    break;
                case 'TEXTAREA':
                    q.push(form.elements[i].name + "=" + encodeURIComponent(form.elements[i].value));
                    break;
                case 'SELECT':
                    switch (form.elements[i].type) {
                    case 'select-one':
                        q.push(form.elements[i].name + "=" + encodeURIComponent(form.elements[i].value));
                        break;
                    case 'select-multiple':
                        for (j = form.elements[i].options.length - 1; j >= 0; j = j - 1) {
                                if (form.elements[i].options[j].selected) {
                                        q.push(form.elements[i].name + "=" + encodeURIComponent(form.elements[i].options[j].value));
                                }
                        }
                        break;
                    }
                    break;
                case 'BUTTON':
                    switch (form.elements[i].type) {
                    case 'reset':
                    case 'submit':
                    case 'button':
                        q.push(form.elements[i].name + "=" + encodeURIComponent(form.elements[i].value));
                        break;
                    }
                    break;
            }
        }

        return q.join("&");
    }

})(window);

(function (window) {
    var nav = window.document.getElementsByTagName("nav")[0];
    var burger = window.document.getElementById("burger");
    burger.addEventListener("click", function(e){
        if (nav) {
            var targetClass = "open";
            if (window.AOS.HasClass(nav, targetClass)) {
                window.AOS.RemoveClass(nav, targetClass);
            } else {
                window.AOS.AddClass(nav, targetClass);
            }
        }
        e.preventDefault();
    });
})(window);

(function (window) {
    var forms = window.document.getElementsByTagName("form");

    for (var i = 0; i < forms.length; i++) {
        window.AOS.CreateForm(forms[i]);
    }

})(window);

(function (window) {

    function updatePhoto (imageUrl) {
        var attribute = 'random-photo';
        var allElements = window.document.getElementsByTagName('*');
        for (var i = 0, n = allElements.length; i < n; i++) {
            if (allElements[i].getAttribute(attribute) !== null) {
                allElements[i].style['background-image'] = 'url("'+imageUrl+'")';
            }
        }
    }

    window.AOS.Http('GET', '/api/random/photo').then(
        function (response) {
            var imageUrl = response.content_base_url+response.image_path;
            updatePhoto(imageUrl);
        }, function (error) {
            console.error("Failed!", error);
        }
    );

})(window);

(function (window) {

    function updateQuote (quote) {
        var attribute = 'random-quote';
        var allElements = window.document.getElementsByTagName('*');
        for (var i = 0, n = allElements.length; i < n; i++) {
            if (allElements[i].getAttribute(attribute) !== null) {
                allElements[i].innerHTML = quote;
            }
        }
    }

    window.AOS.Http('GET', '/api/random/quote').then(
        function (response) {
            updateQuote(response.quote);
        }, function (error) {
            console.error("Failed!", error);
        }
    );

})(window);

(function (window) {

    var randomButton = window.document.getElementById('random');
    var l = Ladda.create(randomButton);

    function goToRandom() {
        l.start();
        window.AOS.Http('GET', '/api/random/publication').then(
            function (response) {
                var url = '/p/'+response.slug;
                l.stop();
                redirect(url);
            }, function (error) {
                l.stop();
                console.error("Failed!", error);
            }
        );
    }

    function redirect (url) {
        window.location.replace(url);
    }

    randomButton.addEventListener('click', function (e) {
        goToRandom();
        e.preventDefault();
    });

    if (window.document.getElementById('load-random-post') !== null) {
        goToRandom();
    }

})(window);

(function ($, algoliasearch) {
        var searchElement = $('#search');
        var client = algoliasearch(searchElement.data('app-id'), searchElement.data('search-key'));
        var index = client.initIndex('production.publications');
        var baseUrl = searchElement.data('base-url');

        function searchCallback(err, content) {
            if (err) { return; }
            if (content.query != $("#inputfield").val()) { return; }
            if (content.hits.length == 0) { $('.results').hide(); return; }

            res = '<div class="publication-list">';
            for (var i = 0; i < content.hits.length; ++i) {
                res += '<article><h2><a href="/p/' + content.hits[i].slug + '">' + content.hits[i].title + '</a></h2></article>';
            }
            res += '</div>';

            searchElement.find('.results').html(res);
            searchElement.find('.results').show();
        }

        function search(query) {
            if (query.length === 0) { searchElement.find('.results').hide(); return; }
            index.search(query, { hitsPerPage: 5, getRankingInfo: 1 }, searchCallback);
        }

        $(document).ready(function() {
            var inputfield = searchElement.find('#inputfield');

            inputfield.keyup(function() {
                search(inputfield.val());
            });
        });
})($, algoliasearch);
