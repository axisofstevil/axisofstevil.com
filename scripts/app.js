(function (window) {
    var serviceUrl = window.document.getElementsByTagName("body")[0].getAttribute('service-url');

    window.AOS = {}

    window.AOS.Http = function (method, url, data) {
        var data = data || '';
        url = serviceUrl + '' + url;

        return new Promise(function(resolve, reject) {
            var req = new XMLHttpRequest();
            req.open(method, url);

            req.onload = function() {
                if (req.status == 200) {
                    resolve(JSON.parse(req.response));
                }
                else {
                    reject(Error(req.statusText));
                }
            };
            req.onerror = function() {
                reject(Error("Something went wrong ... "));
            };
            req.send(data);
        });
    }
})(window);

(function (window) {
    var nav = window.document.getElementsByTagName("nav")[0];
    var burger = window.document.getElementById("burger");
    burger.addEventListener("click", function(e){
        if (nav) {
            var targetClass = "open";
            var classString = nav.className;
            if ((" " + classString + " " ).indexOf( " "+targetClass+" " ) > -1) {
                var newClass = classString.replace(targetClass, "");
            } else {
                var newClass = classString.concat(" "+targetClass);
            }
            nav.className = newClass;
        }
        e.preventDefault();
    });
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
