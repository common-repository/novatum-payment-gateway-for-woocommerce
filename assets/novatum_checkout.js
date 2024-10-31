function formatCardNumber(ccnum) {
    let newval = '';
    const val = ccnum.replace(/\s/g, '');
    for (let i = 0; i < val.length; i++) {
        if (i % 4 === 0 && i > 0) newval = newval.concat(' ');
        newval = newval.concat(val[i]);
    }
    jQuery("#novatum_ccNo").val(newval);
}
