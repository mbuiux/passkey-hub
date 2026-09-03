(function () {
    function normalize(value) {
        return String(value || "").toLowerCase().trim();
    }

    function getElementsByDataAttribute(selector, dataKey, targetId) {
        return Array.prototype.slice
            .call(document.querySelectorAll(selector))
            .filter(function (node) {
                return node.dataset && node.dataset[dataKey] === targetId;
            });
    }

    function applyTableFilters(table) {
        if (!table || !table.id) {
            return;
        }

        var tbody = table.querySelector("tbody");
        if (!tbody) {
            return;
        }

        var searchInputs = getElementsByDataAttribute("input[data-table-target]", "tableTarget", table.id);
        var filterSelects = getElementsByDataAttribute("select[data-table-filter-target]", "tableFilterTarget", table.id);
        var query = searchInputs.length ? normalize(searchInputs[0].value) : "";
        var rows = tbody.querySelectorAll("tr");

        rows.forEach(function (row) {
            var haystack = normalize(row.textContent);
            var matchesSearch = !query || haystack.indexOf(query) !== -1;
            var matchesSelectFilters = true;

            filterSelects.forEach(function (select) {
                var selected = normalize(select.value);
                var key = select.dataset.tableFilterKey || "";
                if (!key || !selected || selected === "all") {
                    return;
                }

                var rowValue = normalize(row.dataset[key] || "");
                if (rowValue !== selected) {
                    matchesSelectFilters = false;
                }
            });

            row.style.display = matchesSearch && matchesSelectFilters ? "" : "none";
        });
    }

    function parseCellValue(text, sortType) {
        var value = (text || "").trim();

        if (sortType === "number") {
            var numeric = parseFloat(value.replace(/[^0-9.-]/g, ""));
            return Number.isNaN(numeric) ? 0 : numeric;
        }

        if (sortType === "date") {
            var timestamp = Date.parse(value);
            return Number.isNaN(timestamp) ? 0 : timestamp;
        }

        return normalize(value);
    }

    function enhanceTable(table) {
        var headers = table.querySelectorAll("thead th");
        var tbody = table.querySelector("tbody");
        if (!tbody || !headers.length) {
            return;
        }

        headers.forEach(function (th, index) {
            th.classList.add("is-sortable");
            th.dataset.sortDir = "desc";

            th.addEventListener("click", function () {
                var sortType = th.dataset.sort || "text";
                var currentDir = th.dataset.sortDir === "asc" ? "desc" : "asc";
                th.dataset.sortDir = currentDir;

                headers.forEach(function (other) {
                    if (other !== th) {
                        other.dataset.sortDir = "desc";
                        other.classList.remove("is-sorted-asc", "is-sorted-desc");
                    }
                });

                var rows = Array.prototype.slice.call(tbody.querySelectorAll("tr"));
                rows.sort(function (a, b) {
                    var aCell = a.children[index];
                    var bCell = b.children[index];
                    var aVal = parseCellValue(aCell ? aCell.textContent : "", sortType);
                    var bVal = parseCellValue(bCell ? bCell.textContent : "", sortType);

                    if (aVal < bVal) {
                        return currentDir === "asc" ? -1 : 1;
                    }
                    if (aVal > bVal) {
                        return currentDir === "asc" ? 1 : -1;
                    }
                    return 0;
                });

                rows.forEach(function (row) {
                    tbody.appendChild(row);
                });

                th.classList.toggle("is-sorted-asc", currentDir === "asc");
                th.classList.toggle("is-sorted-desc", currentDir === "desc");
            });
        });
    }

    function wireSearch(input) {
        var targetId = input.dataset.tableTarget;
        if (!targetId) {
            return;
        }

        var table = document.getElementById(targetId);
        if (!table) {
            return;
        }

        var tbody = table.querySelector("tbody");
        if (!tbody) {
            return;
        }

        input.addEventListener("input", function () {
            applyTableFilters(table);
        });

        applyTableFilters(table);
    }

    function wireSelectFilter(select) {
        var targetId = select.dataset.tableFilterTarget;
        if (!targetId) {
            return;
        }

        var table = document.getElementById(targetId);
        if (!table) {
            return;
        }

        select.addEventListener("change", function () {
            applyTableFilters(table);
        });

        applyTableFilters(table);
    }

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("table[data-enhanced-table='1']").forEach(enhanceTable);
        document.querySelectorAll("input[data-table-target]").forEach(wireSearch);
        document.querySelectorAll("select[data-table-filter-target]").forEach(wireSelectFilter);
    });
})();
