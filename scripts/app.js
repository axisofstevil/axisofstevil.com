(function (window) {
    var serviceUrl = window.document.getElementsByTagName("body")[0].getAttribute('service-url');
    //serviceUrl = 'http://localhost:8000';

    window.AOS = {}

    window.AOS.CreateMessage = function (message, success) {
        success = Boolean(success);

        var messageElement = null;

        if (message) {
            messageElement = window.document.createElement('div');
            window.AOS.AddClass(messageElement, 'box');
            window.AOS.AddClass(messageElement, (success ? 'success' : 'failure'));
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
        form.prefillValues = function () {
            var children = form.elements;
            for (var j = 0; j < children.length; j++) {
                var input = children[j];
                var queryStringValue = window.AOS.GetQueryStringParameter(input.name);
                if (queryStringValue) {
                    input.value = queryStringValue;
                }
            }
        };

        form.prefillValues();
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

    window.AOS.GetQueryStringParameter = function (name) {
        name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");

        var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
            results = regex.exec(location.search);

        return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
    };

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

    window.AOS.gotoRandom = function(onError) {
        window.AOS.Http('GET', '/api/random/publication').then(
            function (response) {
                var url = '/p/'+response.slug;
                window.AOS.redirect(url);
            }, function (error) {
                onError(error);
            }
        );
    }

    window.AOS.redirect = function(url) {
        console.log(window.location.pathname)
        if (window.location.pathname == '/random/') {
            window.location.replace(url);
        } else {
            window.location = url;
        }

    }

    window.AOS.getAnchorByAttribute = function (attribute, value) {
        var anchors = document.getElementsByTagName("A");
        for (var i = 0; i < anchors.length; i++) {
            if (anchors[i].attributes[attribute] && anchors[i].attributes[attribute].value == value) {
                return anchors[i];
            }
        }
        return null;
    }

    window.AOS.gotoNext = function () {
        var nextLink = window.AOS.getAnchorByAttribute('rel', 'next');
        if (nextLink && nextLink.attributes.href) {
            window.location.href = nextLink.attributes.href.value;
        }
    };

    window.AOS.gotoPrevious = function () {
        var previousLink = window.AOS.getAnchorByAttribute('rel', 'prev');
        if (previousLink && previousLink.attributes.href) {
            window.location.href = previousLink.attributes.href.value;
        }
    };

})(window);

(function (window) {
    var nav = window.document.getElementsByTagName("nav")[0];
    var burger = window.document.getElementById("burger");
    if (!burger) {
        return;
    }
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

    var randomButton = window.document.getElementById('get-random');
    if (!randomButton) {
        return;
    }
    var l = Ladda.create(randomButton);

    randomButton.addEventListener('click', function (e) {
        l.start();
        window.AOS.gotoRandom(function (error) {
            console.error("Failed!", error);
            l.stop();
        });
        e.preventDefault();
    });

    if (window.document.getElementById('load-random-post') !== null) {
        window.AOS.gotoRandom();
    }

})(window);

(function (document) {
    var formElements = ['input','textarea']
    var keysEngaged = [];
    var keyCodeMap = {
        37: [window.AOS.gotoPrevious],
        39: [window.AOS.gotoNext],
        82: [window.AOS.gotoRandom]
    }
    document.onkeydown = function (e) {
        e = e || window.event;
        try {
            if (formElements.indexOf(e.target.localName) > -1) {
                return;
            }
        } catch (error) {
            console.log(error)
        }
        var index = keysEngaged.indexOf(e.keyCode);
        if (index == -1) {
            keysEngaged.push(e.keyCode);
        }
    };
    document.onkeyup = function (e) {
        e = e || window.event;
        var index = keysEngaged.indexOf(e.keyCode);
        if (keysEngaged.length == 1 && index > -1) {
            if (keyCodeMap.hasOwnProperty(e.keyCode)) {
                for (var i = 0;  i < keyCodeMap[e.keyCode].length; i++) {
                    keyCodeMap[e.keyCode][i]();
                }
            }
        }
        if (index > -1) {
            keysEngaged.splice(index, 1);
        }
    };
})(document);

(function (window) {
    var mc = new Hammer(document);
    mc.on('swipeleft', function(ev) {
        window.AOS.gotoNext();
    });
    mc.on('swiperight', function(ev) {
        window.AOS.gotoPrevious();
    });
})(window);

(function ($, algoliasearch) {
    var searchElement = $('#search');
    var client = algoliasearch(searchElement.data('app-id'), searchElement.data('search-key'));
    var index = client.initIndex('production.publications');
    var baseUrl = searchElement.data('base-url');

    function searchCallback(err, content) {
        console.log(err, content);
        if (err) { return; }
        if (content.query != $("#inputfield").val()) { return; }
        if (content.hits.length == 0) { $('.results').hide(); return; }

        res = '<div class="search-results"><ul>';
        for (var i = 0; i < content.hits.length; ++i) {
            res += '<li><a href="/p/' + content.hits[i].slug + '">' + content.hits[i].title + '</a></h2></li>';
        }
        res += '</ul></div>';

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
})(jQuery, algoliasearch);

(function (i) {
    var grid = document.querySelector('.grid');

    if (grid) {
        imagesLoaded(grid, function() {
            var iso = new i(grid, {
                // options
                itemSelector: '.grid-item',
                layoutMode: 'packery',
                packery: {
                    gutter: '.gutter-sizer'
                },
                percentPosition: true,
                masonry: {
                    // use element for option
                    columnWidth: '.grid-sizer'
                }
            });
        });
    }
})(Isotope);

(function (window) {
    function lazyLoadImages() {
        var imgDefer = document.getElementsByTagName('img');
        for (var i=0; i<imgDefer.length; i++) {
            if (imgDefer[i].getAttribute('data-src')) {
                imgDefer[i].setAttribute('src',imgDefer[i].getAttribute('data-src'));
            }
        }
    }
    window.onload = lazyLoadImages;
})(window);
