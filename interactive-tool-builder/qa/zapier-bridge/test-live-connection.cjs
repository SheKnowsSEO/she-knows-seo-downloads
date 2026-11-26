const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const endpoint = process.env.SKS_QA_ENDPOINT;
if (!endpoint || !endpoint.startsWith('http://127.0.0.1:8088/')) throw Error('A local isolated test endpoint is required');
const html = fs.readFileSync(__dirname + '/quiz-demo.html', 'utf8');
const script = html.match(/<script>([\s\S]*?)<\/script>/)[1].replace('https://YOUR_WORDPRESS_SITE/wp-json/sks-interactive/v1/submit', endpoint);
const fields = {};
for (const name of ['goal','result','status','show','send','website']) {
  fields[name] = {value:'', textContent:'', disabled:name==='send', events:{}, addEventListener(type, cb){this.events[type]=cb;}, reportValidity(){return !!this.value;}};
}
const root={querySelector(s){return fields[s.replace('#sks-qa-','')];}};
vm.runInNewContext(script,{document:{getElementById(){return root;}},fetch});
(async () => {
 for (const [answer, expected] of [['Choose between options','Try a quiz.'],['Calculate an estimate','Try a calculator.']]) {
  fields.goal.value=answer; fields.goal.events.change(); fields.show.events.click();
  assert.equal(fields.result.textContent,expected);
  await fields.send.events.click();
  assert.match(fields.status.textContent,/sent to the connection/);
  console.log('Sent fictional result:',expected);
 }
})().catch(e=>{console.error(e);process.exit(1);});
