function GridEnabledFormatter(value, row, index){
    var html = '';
    if (value == "no") {
        html += '<i class="fa fa-times"></i>';
    }
    else {
        html += '<i class="fa fa-check"></i>';
    }
    return html;
}