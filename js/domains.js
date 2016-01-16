/* 
 * Copyright 2016 Lukas Metzger <developer@lukas-metzger.com>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

var sort = {
    field: "",
    order: 1
}

$(document).ready(function() {
    requestData();
    
    $('#table-domains>thead>tr>td span').click(function() {
        var field = $(this).siblings('strong').text().toLowerCase();
        if(sort.field == field) {
            if(sort.order == 1) sort.order = 0;
            else sort.field = "";
        } else {
            sort.field = field;
            sort.order = 1;
        }
        $('#table-domains>thead>tr>td span').removeClass("glyphicon-sort-by-attributes glyphicon-sort-by-attributes-alt");
       
        if(sort.field == field) {
            if(sort.order == 1) $(this).addClass("glyphicon-sort-by-attributes");
            else $(this).addClass("glyphicon-sort-by-attributes-alt");
        }
        requestData();
    });
    
    $('#searchName').bind("paste keyup", function() {
        requestData();
    });
    
    $('#searchType').change(function() {
        requestData();
    });
});

function requestData() {
    var restrictions = {};
    
    restrictions.sort = sort;
    
    var searchName = $('#searchName').val();
    if(searchName.length > 0) {
        restrictions.name = searchName;
    }
    
    var searchType = $('#searchType').val();
    if(searchType != "none") {
        restrictions.type = searchType;
    }
    
    $.post(
        "api/domains.php",
        JSON.stringify(restrictions),
        function(data) {
            recreateTable(data);
        },
        "json"
    );
}

function recreateTable(data) {
    $('#table-domains>tbody').empty();
    
    $.each(data, function(index,item) {
       $('<tr></tr>').appendTo('#table-domains>tbody')
            .append('<td>' + item.id + '</td>')
            .append('<td>' + item.name + '</td>')
            .append('<td>' + item.type + '</td>')
            .append('<td>' + item.records + '</td>');
       
    });
    
    $('#table-domains>tbody>tr').click(function() {
        var id = $(this).children('td').first().text();
        var type = $(this).children('td').eq(2).text();
        
        if(type == 'MASTER') {
            location.assign('edit-master.php#' + id);
        }
    });
}