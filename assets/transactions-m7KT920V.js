import{aV as c,aS as f,aT as m}from"./index-CLlKz7fc.js";import{c as p}from"./index-DnalkjpE.js";import"./index-Dzu5tMM-.js";import"./index-g8CWtof-.js";import"./index-BJ0Fymal.js";import"./index-MDF0Zva3.js";import"./index-DdCe_S-x.js";const d=c`
  :host > wui-flex:first-child {
    height: 500px;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: none;
  }

  :host > wui-flex:first-child::-webkit-scrollbar {
    display: none;
  }
`;var u=function(o,t,i,r){var n=arguments.length,e=n<3?t:r===null?r=Object.getOwnPropertyDescriptor(t,i):r,l;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")e=Reflect.decorate(o,t,i,r);else for(var s=o.length-1;s>=0;s--)(l=o[s])&&(e=(n<3?l(e):n>3?l(t,i,e):l(t,i))||e);return n>3&&e&&Object.defineProperty(t,i,e),e};let a=class extends f{render(){return m`
      <wui-flex flexDirection="column" .padding=${["0","3","3","3"]} gap="3">
        <w3m-activity-list page="activity"></w3m-activity-list>
      </wui-flex>
    `}};a.styles=d;a=u([p("w3m-transactions-view")],a);export{a as W3mTransactionsView};
