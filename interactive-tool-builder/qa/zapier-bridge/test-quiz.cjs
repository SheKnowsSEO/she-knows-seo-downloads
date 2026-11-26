const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const html = fs.readFileSync(__dirname + '/quiz-demo.html', 'utf8');
const script = html.match(/<script>([\s\S]*?)<\/script>/)[1];
const fields = {};
for (const name of ['goal','result','status','show','send','website']) {
  fields[name] = {value:'', textContent:'', disabled:name==='send', events:{}, addEventListener(type, cb){this.events[type]=cb;}, reportValidity(){return !!this.value;}};
}
const root={querySelector(s){return fields[s.replace('#sks-qa-','')];}};
let payload, ok=true;
const context={document:{getElementById(){return root;}},fetch:async (url,options)=>{payload=JSON.parse(options.body); return {ok};}};
vm.runInNewContext(script,context);
(async () => {
 fields.show.events.click(); assert.equal(fields.send.disabled,true);
 fields.goal.value='Choose between options'; fields.goal.events.change(); fields.show.events.click();
 assert.equal(fields.result.textContent,'Try a quiz.'); await fields.send.events.click();
 assert.equal(payload.displayed_result,'Try a quiz.'); assert.equal(payload.answers.reader_goal,'Choose between options');
 fields.goal.value='Calculate an estimate'; fields.goal.events.change(); assert.equal(fields.result.textContent,''); assert.equal(fields.send.disabled,true);
 fields.show.events.click(); assert.equal(fields.result.textContent,'Try a calculator.'); await fields.send.events.click();
 assert.equal(payload.displayed_result,'Try a calculator.');
 ok=false; await fields.send.events.click(); assert.match(fields.status.textContent,/did not send/); assert.equal(fields.send.disabled,false);
 console.log('PASS: 2 displayed results, matching payloads, cleared stale result and retry after failure');
})().catch(e=>{console.error(e);process.exit(1);});
