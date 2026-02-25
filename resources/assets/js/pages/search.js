import { itemRender } from '../quickSearch.js';

$('#textSearchForm').on('submit', function (event) {
    event.preventDefault();
    $('#interstitial').show();
    event.target.submit();
});

$('#searchButton').on('click', function (event) {
    event.preventDefault();
    $('#textSearchForm').submit();
});


$("#greekTranslit").autocomplete({
    source: '/kereses/suggestStrong',
    autoFocus: false,
    position: {
        my: "left top",
        at: "left bottom",
        collision: "none"
    }
});

$('#greekTextSearchButton').on('click', function (event) {
    event.preventDefault();
    $('#greekTextSearchForm').submit();
});

$("#greekText").autocomplete({
    source: function (request, response) {
        $.ajax({
            url: "/kereses/suggestGreek",
            dataType: "json",
            data: {
                term: request.term,
                book: $('#greek-search-book').val()
            },
            success: function (data) {
                response(data);
            }
        });
    },
    minLength: 2,
    autoFocus: false,
    position: {
        my: "left top",
        at: "left bottom",
        collision: "none"
    },
    search: (event, ui) => {
        $("#greekTextSearchHitsButtonContent").html('<span class="spinner-border-sm spinner-border"></span> Keresés');
    },
    response: (event, ui) => {
        if (ui.content[0]) {
            const hitCount = ui.content[0].hitCount;
            $("#greekTextSearchHitsButtonContent").html(`${hitCount} találat <i class="bi bi-caret-right"></i>`);
        } else {
            $("#greekTextSearchHitsButtonContent").html("Nincs találat");
        }
    },
    select: (event, ui) => {
        window.location = ui.item.link;
        return false;
    }
}).data("ui-autocomplete")._renderItem = (ul, item) => {
    return itemRender(ul, item);
};




$('#searchInput').autocomplete({
    source: function (request, response) {
        $.ajax({
            url: "/kereses/suggest",
            dataType: "json",
            data: {
                term: request.term,
                book: $('#text-search-book').val(),
                translation: $('#text-search-translation').val(),
                grouping: $('#text-search-grouping').val()
            },
            success: function (data) {
                response(data);
            }
        });

    },
    minLength: 2,
    autoFocus: false,
    position: {
        my: "left top",
        at: "left bottom",
        collision: "none"
    },
    search: (event, ui) => {
        const input = $(event.target);
        const form = input.closest('form');
        form.find("#searchResultsBadge").addClass("d-none");
        form.find("#searchHitsButtonContent").html('<span class="spinner-border-sm spinner-border"></span> Keresés');
    },
    select: (event, ui) => {
        window.location = ui.item.link;
        return false;
    },
    response: (event, ui) => {
        // Find the badge and count span within the same form as the input
        const input = $(event.target);
        const form = input.closest('form');
        const badge = form.find("#searchResultsBadge");
        const countSpan = form.find("#searchResultsCount");
        const buttonContent = form.find("#searchHitsButtonContent");
        
        if (ui.content[0]) {
            const hitCount = ui.content[0].hitCount;
            countSpan.text(hitCount);
            badge.removeClass("d-none");
            // Keep button content as just the search icon and text
            buttonContent.html('<i class="bi-search"></i> Keresés');
            $("#noResultAutocomplete").addClass("hidden");
        } else {
            badge.addClass("d-none");
            buttonContent.html('<i class="bi-search"></i> Keresés');
            if ($("#noResult").length == 0) {
                $("#noResultAutocomplete").removeClass("hidden");
                $("#aiSearchLink").attr('href', '/ai-search?textToSearchAi=' + $("#searchInput").val());
            }
        }
    }

}).data("ui-autocomplete")._renderItem = (ul, item) => {
    return itemRender(ul, item);
};

$('#searchInput').on('input', (event) => {
    if (!event.target.value) {
        const input = $(event.target);
        const form = input.closest('form');
        form.find("#searchResultsBadge").addClass("d-none");
        form.find("#searchHitsButtonContent").html('<i class="bi-search"></i> Keresés');
    }
});

$('#text-search-book').on('change', function (event) {
    $('#searchInput').autocomplete('search');
});

$('#text-search-translation').on('change', function (event) {
    $('#searchInput').autocomplete('search');
});