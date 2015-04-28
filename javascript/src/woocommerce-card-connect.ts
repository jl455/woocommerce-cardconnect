declare let jQuery : any;

export default class WoocommereCardConnect {

  $ : any;
  baseUrl : string;
  cardNumber : string;

  constructor(jQuery : any, csApiEndpoint : string){
    this.$ = jQuery;
    this.baseUrl = csApiEndpoint + '?action=CE&type=json';
  }

  public getToken = (number : string, callback : any) => {
    if(!this.validateCard(number))
      return callback(null, 'Invalid Credit Card Number');

    this.$.get(`${this.baseUrl}&data=${this.cardNumber}`)
      .done(data => this.processRequest(data, callback))
      .fail(data => this.failedRequest(data, callback));
  }

  private validateCard = (number : string) => {
    this.cardNumber = number;
    // @TODO : Additional card number validation here maybe?
    return this.cardNumber.length > 0;
  }

  private processRequest = (data, callback) : void => {
    let processToken = (response) => {
      let {action, data} = response;
      if(action === 'CE')
        callback(data, null);
      else
        callback(null, data);
    }
    eval(data);
  }

  private failedRequest = (data, callback) : void =>
    callback(null, 'Failed to connect to server');

}
