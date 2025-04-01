(function($){function resetSelectItem(selectElementId){$(selectElementId).val(null).trigger('change');const[_,identifier]=selectElementId.split(/(?<=#v2_agent_search_text_)/);const[op,forItemType,agentType]=identifier.replace(/\\:/g,':').split(':');$(`input[name^="pp_exceptions[${forItemType}][${op}][${agentType}][item]"]`).each(function(){$(this).val('');});}
window.resetSelectItem=resetSelectItem;$.fn.DynamicListbox=function(args){var initializeSelect2=function(args2){let selector="#v2_"+args2.search_id;const[op,forItemType,agentType]=args2.topic.replace(/\\:/g,':').split(':');const selectedValues=$(selector).val()||[];let agent_type_lbl=args2.agent_type;if(args2.agent_type=='pp_group'){agent_type_lbl='group';}
$(`input[name^="pp_exceptions[${forItemType}][${op}][${agentType}][item]"]`).remove();selectedValues.forEach(function(value){$('<input>').attr('type','hidden').attr('name',`pp_exceptions[${forItemType}][${op}][${agentType}][item][${value}]`).val('2').appendTo($(selector).parent());});$(selector).select2({placeholder:"Search for a "+agent_type_lbl,dropdownAutoWidth:true,width:'325px',ajax:{url:args2.ajaxurl,dataType:"html",delay:250,data:function(params){let roletext="";if($("#pp_search_role_"+args2.topic).length){roletext=$("#pp_search_role_"+args2.topic).val();}
const umkey=[];const umval=[];for(let i=0;i<6;i++){if($("#pp_search_user_meta_key_"+i+"_"+args2.topic).length){umkey[i]=$("#pp_search_user_meta_key_"+i+"_"+args2.topic).val();umval[i]=$("#pp_search_user_meta_val_"+i+"_"+args2.topic).val();}else{umkey[i]="";umval[i]="";}}
return{pp_agent_search:params.term||"",pp_role_search:roletext,pp_agent_type:args2.agent_type,pp_agent_id:args2.agent_id,pp_topic:args2.topic,pp_usermeta_key:umkey,pp_usermeta_val:umval,pp_omit_admins:ppListbox.omit_admins,pp_metagroups:ppListbox.metagroups,pp_operation:args2.op,pp_context:args2.pp_context,};},processResults:function(data){const options=[];const currentValues=[];$(selector).closest('table.pp-item-exceptions-ui').find('td.pp-current-item-exceptions td input[type="hidden"]').each(function(i,item){currentValues.push($(item).val());});$(data).filter("option").each(function(){const id=$(this).val();if(!currentValues.includes(id)){options.push({id:id,text:$(this).text(),});}});return{results:options,};},cache:true,},}).on('select2:select select2:unselect',function(e){const[op,forItemType,agentType]=args2.topic.replace(/\\:/g,':').split(':');const selectedValues=$(this).val()||[];selectedValues.forEach(function(value){$('<input>').attr('type','hidden').attr('name',`pp_exceptions[${forItemType}][${op}][${agentType}][item][${value}]`).val('2').appendTo($(selector).parent());});if(e.type==='select2:unselect'){$('<input>').attr('type','hidden').attr('name',`pp_exceptions[${forItemType}][${op}][${agentType}][item][${e.params.data.id}]`).val('').appendTo($(selector).parent());}});}
initializeSelect2(args);$('#'+args.search_id).on('keydown',function(e){if(e.keyCode==13){ajax_request($(this).val());e.preventDefault();}});$('input.pp-user-meta-field').on('keydown',function(e){if(e.keyCode==13){ajax_request($('#'+args.search_id).val());e.preventDefault();}});$('#'+args.search_id).next('i.dashicons-search').on('click',function(e){ajax_request($('#'+args.search_id).val());});$("#"+args.button_id).on('click',function(){ajax_request($('#'+args.search_id).val());});var ajax_request=function(stext){$("#"+args.button_id).closest('div').find('.waiting').show();$("#"+args.button_id).prop('disabled',true);$("#"+args.search_id).prop('disabled',true);if(stext==null||stext=='undefined')stext='';if($('#pp_search_role_'+args.topic).length)
var roletext=$('#pp_search_role_'+args.topic).val();else
var roletext='';umkey=[];umval=[];for(i=0;i<6;i++){if($('#pp_search_user_meta_key_'+i+'_'+args.topic).length){umkey[i]=$('#pp_search_user_meta_key_'+i+'_'+args.topic).val();umval[i]=$('#pp_search_user_meta_val_'+i+'_'+args.topic).val();}else{umkey[i]='';umval[i]='';}}
var data={'pp_agent_search':stext,'pp_role_search':roletext,'pp_agent_type':args.agent_type,'pp_agent_id':args.agent_id,'pp_topic':args.topic,'pp_usermeta_key':umkey,'pp_usermeta_val':umval,'pp_omit_admins':ppListbox.omit_admins,'pp_metagroups':ppListbox.metagroups,'pp_operation':args.op,'pp_context':args.pp_context};$.ajax({url:args.ajaxurl,data:data,dataType:"html",success:got_ajax_listbox,error:ajax_failure});}
var got_ajax_listbox=function(data,txtStatus){$('#'+args.results_id).html(data).show();if(typeof document.all=='undefined')
$('#'+args.results_id).trigger('jchange');$("#"+args.button_id).closest('div').find('.waiting').hide();$("#"+args.button_id).prop('disabled',false);$("#"+args.search_id).prop('disabled',false);}
var ajax_failure=function(XMLHttpRequest,textStatus,errorThrown){if(!args.debug)return;$('#'+args.results_id).html('<option value="0"><b style="color:red">'+
XMLHttpRequest.status+':'+
(textStatus?textStatus:'')+
(errorThrown?errorThrown:'')+'</b></option>');}}})(jQuery);