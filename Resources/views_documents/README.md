do not place any template files except the document template files in this directory.

shopware does not support the extend of document templates with a plugin. so we build a workaround for this.

also have a look into `./Subscriber/TemplateRegistration.php::collectTemplateDirForDocuments`
