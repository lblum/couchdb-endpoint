
-- Table: document (d)
CREATE TABLE DOCUMENT 
(
  D_ID VARCHAR2(128) NOT NULL 
, D_REVISION VARCHAR2(128) NOT NULL 
, D_DOCUMENT CLOB NOT NULL 
, CHANGED DATE DEFAULT sysdate 
, CONSTRAINT DOCUMENT_PK PRIMARY KEY 
  (
    D_ID 
  , D_REVISION 
  )
  ENABLE 
);


-- Table: document_update (du)
CREATE TABLE IF NOT EXISTS document_update (
  du_sequence INT NOT NULL,
  d_id VARCHAR2(128) NOT NULL,
  d_revision VARCHAR2(128) NOT NULL,
  changed DATETIME DEFAULT SYSDATE,
  PRIMARY KEY (du_sequence),
  KEY (d_id, d_revision)
);

-- Table: revision (r)
CREATE TABLE IF NOT EXISTS revision (
  r_id VARCHAR2(128) NOT NULL,
  r_revision  CLOB NOT NULL,
  changed TIMESTAMP DEFAULT SYSDATE,
  PRIMARY KEY (r_id)
);
