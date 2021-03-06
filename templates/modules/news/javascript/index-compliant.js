function loadSection(select) {
    window.location = "./?section=" + select.value;
}

function toggleSearch() {
    var categorySwitcher = document.getElementById("category-switcher");
    
    if (categorySwitcher.className == "search-mode") {
        categorySwitcher.className = "category-mode";
    } else {
        categorySwitcher.className = "search-mode";
        document.getElementById("search_terms").focus();
    }
    return false;
}

// Initalize the ellipsis event handlers
clipWithEllipsis(function () {
    var elems = [];
    for (var i = 0; i < 100; i++) { // cap at 100 divs to avoid overloading phone
        var elem = document.getElementById('ellipsis_'+i);
        if (!elem) { break; }
        elems[i] = elem;
    }
    return elems;
});

function submitenter(myfield, e) {
    var keycode;
    if (window.event) {
        keycode = window.event.keyCode;
        
    } else if (e) {
        keycode = e.keyCode;        
        
    } else {
        return true;
    }

    if (keycode == 13) {
       myfield.form.submit();
       return false;
       
    } else {
        return true;        
    }
}
